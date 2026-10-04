<!-- Source: https://github.com/TigerStop/TigerStopSDK/wiki/RS-232:-TigerStop-Serial (cloned 2026-10-03, verbatim) -->

##					Comm Spec 4.0

###	            Serial RS-232 Command interface **TigerStop**Amp version 5.60 6/30/2016

There are both 1 letter and 2 letter commands.  All commands are listed as ASCII characters ([click here for ASCII tabel](http://www.asciitable.com/) ), and for the purposes of this wiki the carriage return command (0xD) will be listed in bold as **cr**, this is identical to pressing enter from a terminal emulating serial.

### Rs232 interface overview:

Most commands are one or two letters followed by an ENTER key.  To read
the current TS position enter "P**cr**".  The Amp 5.x will return "xx.yyy**cr**".  For 
a two letter command you can enter as "MT**cr**" or "M  T**cr**".  Amp 5.x returns an 
X for neg ACK (nack) or an A for ACK. 


### RS232 interface command format:

Some commands like 'D' have 3 ways to use.  
"D**cr**"		display all system data at one time.

"D1**cr**"		display system data item '1'

"D1 1.123**cr**"	enter 1.123 to system data item 1



### Command Overview:
|Comd|Name|Comd|Name| 
|--------|---------|-------|------------|
|'A'|[Analog interface](#a)|'O'|[I/O status](#o)|
'B'|[Comd & Error Log](#b)|'P'|[Comm 1 position comd](#p)|
'C'|[Counter interface](#c)|'R'|[Comm 1 Read comd](#r)|
'D'|[System data interface](#d)|'S'|[Comm 1 Status + S record loader to controller](#s)|
'E'|[Comm 1 Estop](#e)|'U'|[Setlist interface](#u)|
'F'|[Special functions](#f)|'V'|[Comm 1 Version](#v)|
'G'|[Comm 1 Go comd](#g)|'W'|[Comm 2 write comds](#w)|
'H'|[Comm 1 Home comd](#h)|'X'|[jump to loader](#x)|
'I'|[Comm 1 Input comd](#i)|'Z'|[RTOS status](#z)|
'L'|[comm 2 Look comds](#l)|'0'|[Display 3phase motor drive data](#0)|
'M'|[Move interface](#m)|'?'|[Help](#?)|


### Explanations And Examples:
note: r = read only, w = write only and r/w = read and write
	
 
		
#### 'A'	
##### Analog interface
        There are 6 analog sub-commands
			0 = r all 5 channels
			1 = r Drive current
			2 = r High Voltage
			3 = r +15 volts
			4 = r Amp temp in C
			5 = r +24 volts
	**(ex.)** 

               Serial send:"A**cr**"= (0x41)(0x0D)
	
	Serial return:
		"1, Drive Current,   1.1**cr**"
		"2, High Voltage,  160.0**cr**"
		"3, +15 Volts,      15.0**cr**"
		"4, Amp temp,      25.9c, 78.6f**cr**"
		"5, +24 volt,      24.6**cr**"

	Serial send: "A2**cr**" = (0x41)(0x32)(0x0D)
	
	Serial ret:
		"A data**cr**", A = ACK (0x41), data = return data,**cr** = Enter (oxoD)

	Serial Error:
		"X EC=2 Bad Arg", index outside 0-5 range, X = NAK, EC = error
		code, Bad Arg = error text

#### 'B'	
##### Command & Error Log

	B sent with no number will display the last 20 entries		
        B with an index number will display entries starting
	  with your index and working back 20 entries.	
	B20 will display entries 20-40 with 20 the newest
	    and 40 the oldest
        (**ex.)**
        Serial send:	"B**cr**" = (0x42)(0x0D)
	Serial ret:
	"Comdt= d,Comd= w,Src = -1,P Gain= 31.000,time= 0d:0h:12m:23s"
	"Comdt= M,Comd= W,Src= -1,Move to= 50.000,time= 0d:0h:10m:13s"
	"ERROR = Move Max Limit, time = 0d:0h:10m:13s**cr**"
	Comdt is the command type, same as the command overview list above
	Comd is the sub command for the given command type ie MG
	Src is the source of the command for the data write
		* Controller = 18
		* Serial is -1
	time is ON time from first power up
	'B' will display the last 20 entries with the newest at top of list.  
	
	
	

#### 'C'	
##### Counter interface

        There are 25 subcomds
			C or C0 = r all subcomds will display on serial
			Cn = r n entry, n = 0-25
        **(ex.)**        
	Serial send:	"C**cr**" = (0x43)(0x0D)	
	Serial return:
	 1,      UpTime =   6020
	 2,    PowerOns =      1
	 3,     WLMoves =      0
	 4, ManualMoves =      0
	 5, Total Moves =      0
	 6,    SerComds =      9
	 7,    CanComds =      0
	 8, Total Comds =      9
	 9, CNTRuserWrt =      0
	10,  CNTRbuWrts =      0
	11,  SIuserWrts =      0
	12,    SIbuWrts =      0
	13,  SIfactWrts =      0
	14, Total Inchs =      0, ft=0.0, miles=0.000, meters=0.000
	15, C log Index =      0
	16, E log Index =      0
	19,Amp Temp Avg =    0.0c    32.0f
	20,Amp Temp Max =    0.0c    32.0f
	21,MotorAmp Avg =    0.0,  HP = 0.0
	22,MotorAmp Max =    0.0,  HP = 0.0
	23,Tool Time ms =      0
	24,IJ Hard Errs =      0
	25,IJ Soft Errs =      0

		
	Serial send: "CX**cr**" = (0x43)(0x31 0x38)(0x0D)	
	Serial ret:
		"C data**cr**", C = ACK (0x43), data = return data,**cr** = Enter (oxoD)
	Serial Error:
		"X EC=2 Bad Arg", index outside 0-5 range, X = NAK, EC = error
 		code, Bad Arg = error text


###  'D'	
#### System data interface

	Serial: "D?**cr**" list of new D commands
	Serial ret:
	"D"  all data but Controller and Timers
	"DC" controller data
	"DD" drive data
	"DM" move data
	"DT" timer data
	"D15 ?" debug help

	Serial: "D15 ?**cr**" list of D15 debug enables
	Serial ret:
	Can debug =    32 - 0x20,
	MoveErrs  =    64 - 0x40,
	Drv LB    =   128 - 0x80,
	Pnt debug =   256 - 0x100
	MemSegs"y"=   512 - 0x200,
	I/O debug =  1024 - 0x400,
	PntDummy  =  8192 - 0x2000,
	Position  = 16384 - 0x4000,
	Debug     = 32768 - 0x8000
	SingleStep= 65535 - 0x10000,
	State Disp=131972 - 0x20000

##### 'D'

        There are 124 system data subcomds
			D or D0 wil display most 102 entries, serial only
			Dn = r/w n entry, n = 0-123

	'D' will return all SystemInfo data but Timers and Controller only data
	Serial:	"D**cr**" = (0x44)(0x0D)

	Serial return:
          1-P Gain  =2.0      2-I Gain  =0.50     3-D Gain  =20.0     4-Vel In  =25.0
          5-Vel Out =25.0     6-Acc In  =75.0     7-Acc Out =75.0     8-Dec In  =75.0
          9-Dec Out =75.0    10-Lim Max =78.0    11-Lim Min =6.0     12-IOP Baud=9600
          13-Scale   =2.36205 14-Dither  =50      15-Debug   =0       16-Clamp 1 =5.0
          17-Clamp 2 =-5.0    18-Position=78.0    19-PrtType =7       20-PrtBaud =9600
          21-CommBaud=19200   22-JetOffse=3.0     23-Kerf    =0.125   24-Head Cut=2.0
          25-Tail Cut=2.0     26-Outfeed =0       27-Backoff =0       28-Retract =2.0
          29-Ret Offs=0       30-Feed Haz=0       31-Load Off=0       32-Max SPL =178.0
          33-opti Sco=2.0     34-opti Tim=2.0     35-opti Pen=1.0     36-Move Del=0
          37-Lash    =0.030   38-SM Table=0       40-Motor Ty=0       41-ME Zero =1
          42-language=0       43-conSleep=0       44-MMEnable=0       45-contrast=100
          46-prt Name=0       47-prt Cuts=0       48-ret type=1       49-IOReadMa=0
          50-IOWritMa=0       51-presetty=0       52-wastefir=0       53-conPW  =  ******
          64-ComPanel=2.0     77-S cpi   =5.50    78-L cpi   =2.825   79-Pnt Dly =-0.20
          80-TH LV M =525     81-TH LV B =12000   82-Can Dly =3.0     88-SC Rel  =0
          89-TH HV M =275     90-TH HV B =12000   91-StallMEP=1.0     92-StallMES=1.0
          93-Run MEP =1.0     94-Run MES =0.20    95-RateGain=50.0    96-RateThes=0.50
          97-SetPntSy=1.0     98-Timer   =0       99-FindVel =0       100-FindErr=300.0
          103-IR Type=0       105-Mtr CPR=4000    106-Mtr Pol=8       107-Adv Set=0.20
          108-DrvAccL=7000    109-TM Offs=6.0     110-TM Enab=0       111-Fast Ca=1
          112-Fast Un=0       113-QFilter=14.0    114-DFilter=10.0    115-EFilter=2.0
          116-Brk TYP=0       117-PF_Up_P=24.0    118-Rev Jog=0       119-VJ Font=0
          120-Crayon =0       121-UV_IR_O=1.950   122-PF_Offs=29.250  123-D Margi=0
	

	--->>>> NOTE NEVER change the valuses for 105-108 <<<<---

#### 'DD'

	'DD' will return data used in Drive calculations
	Serial ret:

	1-P Gain  =2.0    2-I Gain  =0.50     3-D Gain  =20.0     4-Vel In  =25.0
	5-Vel Out =25.0   6-Acc In  =75.0     7-Acc Out =75.0     8-Dec In  =75.0
	9-Dec Out =75.0   10-Lim Max =78.0    11-Lim Min =6.0     14-Dither  =35
	105-Pd Gain=0.50  106-Id Gain=0       107-Pq Gain=0.50    108-Iq Gain=0

	'DM' will return data used in movement control
	Serial ret:
	10-Lim Max =78.0   11-Lim Min =6.0    80-TH LV M =600    81-TH LV B =15000
	89-TH HV M =300    90-TH HV B =8000   91-StallMEP=1.0    92-StallMES=1.0
	93-Run MEP =1.0    94-Run MES =0.20   95-BrkVel  =32.0   96-BrkGain%=30.0
	97-SetPntSy=1.0    98-FindMax =0      99-FindVel =10.0
#### 'DT'
	'DT' will return Timer data
	Serial ret:
	54-ClampOn_=0.10   55-SawOn_D =0.10   56-DMOff_TO=5.0    57-TAOn_TO =5.0
	58-TAOff_TO=5.0    59-DMOn_TO =5.0    60-ClampOff=0.10   61-SawCyc_D=0.10
	62-RSD_Rdy_=0.70   63-Timer10 =0.20
#### 'DC'
	'DC' will return data used by the Controller only, not used by the amp
	Serial ret:
	39-Sys Type=0      42-language=0      65-**cr**ossCal=60.0   66-**cr**ossAla=0
	67-HandyOpt=0      68-Waste   =2.0    69-DropBox =2.0    70-Defect  =0
	71-L Range =0.20   72-L Count =0      73-Laser ME=0      74-L Limit =144.0
	75-L Ref   =5.0    76-Cld Sty =0      83-Inf     =0      84-PetClear=0
	85-Pet Dim =0      86-Banana1 =24.0   87-Banana2 =36.0   101-DrillMo=0
	102-MMRatio=0


		
	Serial: "DX**cr**", D = (0x44), X = 1-102 (0x31-0x31 0x30 0x32), **cr** = Enter (0x0D)
	
	Serial ret:
		"d5
		25.000"

	Serial Error:
		"d200
		   X EC=3 BAD INDEX"
		
#### 'E'
##### E Stop is redirected to [ME](#m) for Move E Stop
####  'F"
##### 	Special functions
 
        There are 7 sub-commands
			1 = w Save Factory 
			2 = w Load Factory
			3 = w Load Defaults
			4 = w Load HV Defaults
			5 = w Controller loader interface
			6 = w Sleep mode
			7 = w Wake up
			Note: you will get unpredictable results for 6 and 7 
			if the controller is in sleep mode.

	Serial: "FX**cr**", F = (0x46), X = 1-4 (0x31-0x34), **cr** = Enter (0x0D)
	Serial Ret:
		"A**cr**", A = ACK, **cr** = Enter (oxoD)
	Serial Error:
		"X EC= N TEXT",X = NAK, N = Error Code, TEXT = EC Text
#### 'G'	
##### The Go command
               This command is redirected to the [MG](#m) command.

Note 1:
		The MG command also supports Velocity, ramps for one movement.  To 
		use the full command enter

Serial:
		"MG 123.456 23.000 50.000 45.000**cr**"
		M 	= move command
		G 	= Go sub comd
		123.456	= position to move to
		23.000	= velocity for this movement
		50.000	= accell for this movement
		45.000	= decel for this movement
		**cr** = Enter

#### 'H' 
##### The Home command is redirected to [MH](#m) under moves.
#### 'L'
#####   	Look commands

                There are 4 sub-commands
			p = r position
			R = r Raw Data
			S = r State
			T = r Latched
			w = r read word from comm controller (ver5.41)

		The LR command will read 8 bits of data. 
                The bottom 4 bits are read from the amp port and 
		the top 6 bits come from IO1 on the first comm panel. Plus
		one new command has been added, "LW" for look word

	Serial: serial commands only
		"LW#**cr**"	Will read 12 bits from comm panel 1 I/O 1 and I/O 2
			"#" is the comm controller # 1-4, if no '#' is given 
			then 1 is assumed.
		"LW**cr**"	read word from comm panel 1
		"LW1**cr**"	read word from comm panel 1.
	

### 'M'
####	Move interface

		There are 6 sub-commands
			MG =  go to position
			MH = Home
			MS =  Stop
			ME = E Stop
			MM =  Min-Max 
			MD = drive Test

	Serial: "MG123.456**cr**", M = 0x4D, G = 0x47, 123.456 = position, 
		**cr** = Enter (0x0D)
		"MH**cr**", M = 0x4D, H = 0x48, **cr** = Enter (0x0D)
		"MS**cr**", M = 0x4D, S = 0x53, **cr** = Enter (0x0D)
		"ME**cr**", M = 0x4D, E = 0x45, **cr** = Enter (0x0D)
		"MM**cr**", M = 0x4D, M = 0x4D, **cr** = Enter (0x0D)
		"MD**cr**", M = 0x4D, D = 0x44, **cr** = Enter (0x0D)

	Serial Ret:
		MG has 2 acks, "MGS xx.xxx" for Move Go Start and xx.xxx for traget position
				"MGF" for move fnished.
		MS has 1 ack , MSS for comd ack

		ME has 1 ack , MES for comd ack

		MH has 3 acks, MGF for each of the 3 movements.

		MM has 6 acks, MGF for each of the 6 movements

		MD has 

	Serial Error:
		"X EC= N TEXT",X = NAK, N = Error Code, TEXT = EC Text


#### 'O'
#####	I/O status

	Serial:	"O**cr**", O = 0x4F,**cr** = Enter (ox0D)
		
	Serial Ret:
	"SE=OFF,DM=ON,TA=OFF,L1=ON,L2=ON,FS=OFF,FST=OFF,SC=ON,OSM=0,SM=65**cr**"

#### 'P'
#####	Position 

#### 's'
#####	Status + S record loader to controller

#### 'U"
##### Setlist interface

		There are 3 subcomds
			H = w Header to amp to start the SetList DL
			K = w Keyboard char to amp
			S = w Set data		

#### 'V'
#####	 Version 

#### 'W'
##### 	Write comdmands
	Serial: serial commands only
		"WB 3**cr**"	will write a 3 to the bottom 2 bits on the amp and 
				set the top 2 bits to 0 along with the 6 bits on 
				the first port on the first Comm controller.  This 
				is the SAME action as the 4.72 WB to both of the 
				IO conntectors on the 2C amp.  The amp2C 4 bit port 
				covers bits 0,1,2,3 the 6 bit port covers bits 2,3,
				4,5,6,7 and yes bits 2 and 3 are the same for both 
				ports. The difference between the 2C and 3A is the 
				6 bit port is the first port on the first Comm 
				controller for 3A(5.XX).
				BIT 	pin
				0 (1)	5 on amp		SE AIK SIK
				1 (2)	2 on amp		DM AIK SIK
				2 (4)	4 on amp & 2 on I/O1 	FS AIK
				3 (8)	3 on amp & 3 on I/O1	TA AIK
				4 (10)	4 on I/O 1		Beeper	SAW
				5 (20)	5 on I/O 1		Printer	SAW
				6 (40)	6 on I/O 2		Clamp1	SAW
				7 (80)	7 on I/O 2		Clamp2	SAW



		The WW comd is new to v5.41.  Data can be in decimal,hex 
		or octal.
		125 	will be taken as a deciaml number
		0x7D	will be taken as a HEX number
		0175	will be taken as an octal number

		"WW# wData wMask**cr**" Write Word with mask, # = 1,2,3,4 for 1 of 4
				comm controllers.  If # is not used 1 is assumed.
				Logic for word write operation.

				"pins = pins AND ~wMask OR wData"

				to turn on bit 2	wData = 4, wMAsk = 4
				to turn off bit 2	wData = 0, wMask = 4

				If you dont give a wMask then 0x0FFF is assumed meaning
                                any bit you do not turn on with the wData 
				will be turned off (all 12 bits will be updated) 

				port 1 = 6 bits 0,1,2,3,4,5, port 2 = 6,7,8,9,10,
				11,only 12 bits are used.

				BIT	pin
				0 (1)	2 on I/O 1	Brake control
				1 (2)	3 on I/O 1	res.
				2 (4)	4 on I/O 1	Beeper		TigerSaw
				3 (8)	5 on I/O 1	Print trigger	TigerSaw
				4 (10)	6 on I/O 1	Clamp1 & SwingClamp
				5 (20)	7 on I/O 1	Clamp2
				6 (40)	2 on I/O 2	Top Clamp	TigerSaw
				7 (80)	3 on I/O 2	Learning Clamp	TigerSaw
				8 (100)	4 on I/O 2	
				9 (200)	5 on I/O 2
				10(400)	6 on I/O 2	PusherFoot	TigerSaw
				11(800)	7 on I/O 2	


#### 'Z'	
##### RTOS status

	Serial send:	"Z**cr**", Z = 0x5A,**cr** = Enter (ox0D)

	Serial Return:
		UpTime = 0d:0h:4m:47s LFS = 5400 MemSegs = 2/4
		Task Name     Pri  Stack f/u
		SI Mutex Pri   3      0/   0
		CanTask        4    504/ 296
		Rs232Task      5    400/ 400
		XmitTask       6   1556/ 244
		ComdTask       7    544/1256
		SM Task        8    528/ 272
		MoveTask       9   1028/ 172
		Analog Task   10    468/ 332
		FunTask       11    660/ 140
		SITask        13    440/ 160
		CntrTask      14    656/ 144
		HK Task       16    404/ 396
		pos =  0.000, drive =    10

	LFS  Largest Free Segment, largest free memory segment from the memory pool.
	MemSegs X/Y, X = memory segments allocated now, Y = Max number allocated 

#### '0'	
##### Display 3phase motor drive data

	Serial: "0**cr**", 0 = 0x30,**cr** = Enter (0x0D)

	lTarget =       0, lErr =    0
	ang =    0, drv =    0, off =    0
	snA =    0, snB =    0, snC =    0
	dv1 =    0, dv2 =    0, dv3 =    0


#### '?'	
##### Help

	Serial:	"?**cr**", ? = 0x3F,**cr** = Enter (0x0D)

	Serial Ret:
	A ->Analog#-RD,     B ->comd Log-D,     C ->Counters#-RD,   D ->Data#-RWD
	DC->Data Controller,DD->Data Drive,     DM->Data Move,      DT->Data Timer
	D?->Data Help,      G ->Go pos,vel,accel,decel-W
	ME->Move Estop-W,   MG->Move Go pos,vel,accel ,decel-W,     MH-> Move Home-W
	MM-> Move MinMax-W, MS->Move Stop-W,
	O ->Input-R,        P ->Position-R,     V ->Version-R
	XYZZY ->Jump to Loader-W,               Z ->OS Data-D       ? help-R
	Comd# where # is 0 - n, 0 = display all, ComdN = read Comd index n
	R = read used with # to read one item at a time,W same as R but writing
	D = Display, all items at once in term mode only


#### Status States:
		PS_HALT	= 0	drive is online and not moving
		PS_ACCEL	= 1	drive is accelerating
		PS_CONST	= 2	drive is at constant speed
		PS_DECEL	= 3	drive is decelerating
		PS_DISABLE	= 4
		PS_LASHSEEK	= 5	drive is doing lash seek
		PS_DELAY	= 6
		PS_STOP	= 7	drive is decelerating to stop NOT at Goal
		PS_SLEEP	= 8	drive is sleeping
		PS_DDRIVE	= 9	drive is under direct control of controller

#### Return Error Codes:
		ERR_NONE,	= 0 	no errors
		ERR_COMD,	= 1	bad command type
		ERR_ARG,	= 2	bad Arg
		ERR_RS232INDEX,	= 3	bad index
		ERR_MOVEPARA,	= 4	bad parameter
		ERR_MOVEBUSY,	= 5	Move system is in use at this time
		ERR_MOVEMAX,	= 6	MAX move error
		ERR_MOVEMIN,	= 7	MIN move error
		ERR_MOVEFAR,	= 8	Far limit trip error
		ERR_MOVENEAR,	= 9	Near limit trip error
		ERR_MOVENOTRDY,	= 10	sub system not ready for command
		ERR_FLASHWRT,	= 11	error in writing to flash
		ERR_SERECNA,	= 12	Enable Code NOT Available
		ERR_SERECINDEX,	= 13	bad EC index
		ERR_SEREXIST,	= 14	Serial number already exist
		ERR_ECDUP,	= 15	EC already exist in EC history buffer
		ERR_ECTO,	= 16	EC Time Out on wirte operation
		ERR_FACT_CS,	= 17	factory checksum error
		ERR_H1MOVE,	= 18	home stge 1 error
		ERR_H2MOVE,	= 19	home stge 2 error
		ERR_H3MOVE,	= 20	home stge 3 error
		ERR_MM1MOVE,	= 21	MinMax 1 error
		ERR_MM2MOVE,	= 22	MinMax 2 error
		ERR_MM3MOVE,	= 23	MinMax 3 error
		ERR_MM4MOVE,	= 24	MinMax 4 error
		ERR_MM5MOVE,	= 25	MinMax 5 error
		ERR_SOC,	= 26	Safe Operation Code error in SM
		ERR_NOTKNOWN,	= 27	error not known
		ERR_AIK_NE,	= 28	AIK system not enabled
		ERR_FA_NE,	= 29	AIK Full Auto not enabled
		 ERR_ESTOP,	= 30	ESTOP
		ERR_MAXERR,	= 31	over max error limit during movement
		ERR_BAD_EC,	= 32	bad EC does for current serial number
		ERR_MLA,	= 33	Miss-aligned access error
		ERR_COMDQF,	= 34	Command Que is full, message lost
		ERR_ITRIP,	= 35	current trip in amp module
		ERR_RSDR,	= 36	the RSD return from the client timed out
		ERR_DMALLOC,	= 37	Malloc over run error

#### Selectable Baud rates:
		9600, 19200, 38400, 57600*, 115200, 230400
		* default buad


#### Debug enable bits  

	    32 	0x20    	send data from the amp-con comm out the serial port
	    64	0x40	displays  Error values at knees
	   128	0x80	loopback target to pos, no movement but pid happy
	   256 	0x100	label print via the onboard serail for debuging
	   512	0x200	debug mem allocation requester
	  1024	0x400	turn in verbose for the IO controller
	  2048	0x800	drives the IO pin 1 (saw enable) 2 x SI_IOREAD in MS
	  4096	0x1000	Flips the trigger for the inkjet printer
	  8192	0x2000	print 4 dummy fields, inkjet
	 16384 	0x4000	display position and move errors
	 32768 	0x8000	debug data display
	 65536 	0x10000	single step state machine
	131972 	0x20000	display the state number at change in state
	262144 	0x40000	display the 3 phase data every 200ms if moving

	**(ex)** "D15 0x40" = display max errors at end of movement.

        **Note:** You can also you can turn on more then one 
        item at a time by adding the number for the items you want on. 
        **(ex.)**To turn on amp-con data and Error values both  32 + 64 = 96 
	so set D15 to 96.
