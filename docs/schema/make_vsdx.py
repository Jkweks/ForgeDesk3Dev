import csv, math, zipfile, collections, sys
from xml.sax.saxutils import escape
D=sys.argv[1]
cols=list(csv.DictReader(open(D+'/columns.csv')))
fks=list(csv.DictReader(open(D+'/fks.csv')))
tables=collections.OrderedDict()
for c in cols: tables.setdefault(c['Table'],[]).append(c)
fkcols=collections.defaultdict(set)
for f in fks: fkcols[f['FromTable']].add(f['FromColumn'])
short={'character varying':'varchar','timestamp without time zone':'timestamp','timestamp with time zone':'timestamptz','double precision':'double','integer':'int','boolean':'bool'}
# groups by prefix; small prefixes -> core
pre=lambda t:t.split('_')[0]
cnt=collections.Counter(pre(t) for t in tables)
grp=lambda t: pre(t) if cnt[pre(t)]>=3 else 'core'
groups=collections.OrderedDict()
for t in sorted(tables): groups.setdefault(grp(t),[]).append(t)
order=sorted(groups,key=lambda g:(g=='core',-len(groups[g])))
palette=['#1f6feb','#2da44e','#bf8700','#cf222e','#8250df','#0a7d8c','#b35900','#6e7781','#a5337f','#3d7a3d']
W=2.5; LH=0.15; HDR=0.28; GAP=0.5; ROWW=60.0
pos={}; gl=[]; y=0.0
for gi,g in enumerate(order):
    gl.append((g,y,palette[gi%len(palette)])); y+=0.6
    x=0; rowh=0; ts=groups[g]
    for t in ts:
        h=HDR+len(tables[t])*LH+0.1
        if x+W>ROWW and x>0: y+=rowh+GAP; x=0; rowh=0
        pos[t]=(x,y,h,palette[gi%len(palette)]); x+=W+GAP; rowh=max(rowh,h)
    y+=rowh+GAP*2
PH=y+1; PW=ROWW+2; OX=1.0
# top-down (x,y,h) -> visio center
def center(t):
    x,yt,h,_=pos[t]; return OX+x+W/2, PH-1-(yt+h/2)
nid=[0]
def nxt(): nid[0]+=1; return nid[0]
shapes=[]
def cell(n,v,f=None): return f"<Cell N='{n}' V='{v}'"+(f" F='{f}'" if f else '')+"/>"
def clip(cx,cy,hw,hh,dx,dy):
    if dx==0 and dy==0: return 0,0
    s=min(hw/abs(dx) if dx else 1e9, hh/abs(dy) if dy else 1e9)
    return dx*s,dy*s
# connectors first (behind)
seen=set()
for f in fks:
    a,b=f['FromTable'],f['ToTable']
    if a==b or (a,b) in seen: continue
    seen.add((a,b))
    ax,ay=center(a); bx,by=center(b)
    ha=pos[a][2]/2; hb=pos[b][2]/2
    dx,dy=bx-ax,by-ay
    sx,sy=clip(ax,ay,W/2,ha,dx,dy); ex,ey=clip(bx,by,W/2,hb,-dx,-dy)
    x1,y1=ax+sx,ay+sy; x2,y2=bx+ex,by+ey
    L=math.hypot(x2-x1,y2-y1)
    if L<0.01: continue
    ang=math.atan2(y2-y1,x2-x1)
    i=nxt()
    shapes.append(f"<Shape ID='{i}' Type='Shape' LineStyle='3' FillStyle='3' TextStyle='3' NameU='FK {escape(a)}-{escape(b)}'>"+
      cell('PinX',(x1+x2)/2)+cell('PinY',(y1+y2)/2)+cell('Width',L)+cell('Height',0)+cell('LocPinX',L/2)+cell('LocPinY',0)+cell('Angle',ang)+
      cell('BeginX',x1)+cell('BeginY',y1)+cell('EndX',x2)+cell('EndY',y2)+
      cell('LineColor','#8c959f')+cell('LineWeight',0.008)+cell('EndArrow',4)+cell('EndArrowSize',1)+cell('FillPattern',0)+
      "<Section N='Geometry' IX='0'>"+cell('NoFill',1)+cell('NoLine',0)+cell('NoShow',0)+cell('NoSnap',0)+cell('NoQuickDrag',0)+
      "<Row T='MoveTo' IX='1'>"+cell('X',0)+cell('Y',0)+"</Row><Row T='LineTo' IX='2'>"+cell('X',L,'Width')+cell('Y',0)+"</Row></Section></Shape>")
def hexlight(c):
    r,g,b=int(c[1:3],16),int(c[3:5],16),int(c[5:7],16)
    return '#%02x%02x%02x'%tuple(int(v+(255-v)*0.9) for v in (r,g,b))
for t in tables:
    x,yt,h,col=pos[t]; cx,cy=center(t); i=nxt()
    lines=[]
    for c in tables[t]:
        tag='PK' if c['Key'] else ('FK' if c['Column'] in fkcols[t] else '  ')
        lines.append(f"{tag} {c['Column']} : {short.get(c['Type'],c['Type'])}")
    text="<Text><pp IX='0'/><cp IX='0'/>"+escape(t)+"\n<pp IX='1'/><cp IX='1'/>"+escape('\n'.join(lines))+"</Text>"
    shapes.append(f"<Shape ID='{i}' Type='Shape' LineStyle='3' FillStyle='3' TextStyle='3' NameU='{escape(t)}'>"+
      cell('PinX',cx)+cell('PinY',cy)+cell('Width',W)+cell('Height',h)+cell('LocPinX',W/2,'Width*0.5')+cell('LocPinY',h/2,'Height*0.5')+cell('Angle',0)+
      cell('FillForegnd',hexlight(col))+cell('FillPattern',1)+cell('LineColor',col)+cell('LineWeight',0.012)+
      cell('LeftMargin',0.06)+cell('TopMargin',0.05)+cell('RightMargin',0.04)+cell('BottomMargin',0.04)+cell('VerticalAlign',0)+
      "<Section N='Character'><Row IX='0'>"+cell('Style',1)+cell('Size',0.1667)+cell('Color',col)+"</Row><Row IX='1'>"+cell('Style',0)+cell('Size',0.1111)+cell('Color','#24292f')+"</Row></Section>"+
      "<Section N='Paragraph'><Row IX='0'>"+cell('HorzAlign',0)+"</Row><Row IX='1'>"+cell('HorzAlign',0)+"</Row></Section>"+
      "<Section N='Geometry' IX='0'>"+cell('NoFill',0)+cell('NoLine',0)+cell('NoShow',0)+cell('NoSnap',0)+cell('NoQuickDrag',0)+
      "<Row T='MoveTo' IX='1'>"+cell('X',0)+cell('Y',0)+"</Row>"+
      "<Row T='LineTo' IX='2'>"+cell('X',W,'Width')+cell('Y',0)+"</Row>"+
      "<Row T='LineTo' IX='3'>"+cell('X',W,'Width')+cell('Y',h,'Height')+"</Row>"+
      "<Row T='LineTo' IX='4'>"+cell('X',0)+cell('Y',h,'Height')+"</Row>"+
      "<Row T='LineTo' IX='5'>"+cell('X',0)+cell('Y',0)+"</Row></Section>"+text+"</Shape>")
for g,gy,col in gl:
    i=nxt(); w=12; h=0.4
    shapes.append(f"<Shape ID='{i}' Type='Shape' LineStyle='3' FillStyle='3' TextStyle='3' NameU='Group {escape(g)}'>"+cell('PinX',OX+w/2)+cell('PinY',PH-1-gy-h/2)+cell('Width',w)+cell('Height',h)+cell('LocPinX',w/2)+cell('LocPinY',h/2)+cell('Angle',0)+cell('FillPattern',0)+cell('LinePattern',0)+cell('VerticalAlign',1)+
      "<Section N='Character'><Row IX='0'>"+cell('Style',1)+cell('Size',0.3333)+cell('Color',col)+"</Row></Section><Section N='Paragraph'><Row IX='0'>"+cell('HorzAlign',0)+"</Row></Section>"+
      f"<Text><pp IX='0'/><cp IX='0'/>{escape(g.upper())}  ({len(groups[g])} tables)</Text></Shape>")
import re, zipfile, sys, os
TEMPLATE=sys.argv[2]   # vsdx file known to open in Visio (from the python 'vsdx' package)
src=zipfile.ZipFile(TEMPLATE)
NS="xmlns='http://schemas.microsoft.com/office/visio/2012/main' xmlns:r='http://schemas.openxmlformats.org/officeDocument/2006/relationships' xml:space='preserve'"
H="<?xml version='1.0' encoding='utf-8' ?>\n"
page=H+f"<PageContents {NS}><Shapes>"+''.join(shapes)+"</Shapes></PageContents>"
pages=src.read('visio/pages/pages.xml').decode()
pages=re.sub(r"(N='PageWidth' V=')[^']*",lambda m:m.group(1)+str(PW),pages)
pages=re.sub(r"(N='PageHeight' V=')[^']*",lambda m:m.group(1)+str(PH),pages)
pages=re.sub(r"ViewCenterX='[^']*'","ViewCenterX='%s'"%(PW/2),pages)
pages=re.sub(r"ViewCenterY='[^']*'","ViewCenterY='%s'"%(PH-10),pages)
win=src.read('visio/windows.xml').decode()
win=re.sub(r"ViewCenterX='[^']*'","ViewCenterX='%s'"%(PW/2),win)
win=re.sub(r"ViewCenterY='[^']*'","ViewCenterY='%s'"%(PH-10),win)
win=win.replace("ViewScale='1'","ViewScale='0.1'")
pages=pages.replace("ViewScale='1'","ViewScale='0.1'")
rels=src.read('_rels/.rels').decode()
rels=re.sub(r'<Relationship Id="rId2"[^>]*thumbnail[^>]*/>','',rels)
over={'visio/pages/page1.xml':page,'visio/pages/pages.xml':pages,'visio/windows.xml':win,'_rels/.rels':rels}
import xml.dom.minidom
out=D+'/ForgeDesk_schema.vsdx'
with zipfile.ZipFile(out,'w',zipfile.ZIP_DEFLATED) as z:
    for n in src.namelist():
        if n=='docProps/thumbnail.emf': continue
        data=over[n] if n in over else src.read(n)
        if n.endswith('.xml') or n.endswith('.rels'): xml.dom.minidom.parseString(data if isinstance(data,bytes) else data.encode())
        z.writestr(n,data)
print(len(tables),'tables',len(seen),'connectors',f'page {PW:.0f}x{PH:.0f} in')
