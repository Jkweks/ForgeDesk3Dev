<!-- Source: https://github.com/TigerStop/TigerStopSDK/wiki/Scaling-TigerStops (cloned 2026-10-03, verbatim) -->

##			Simple way to scale a TigerStop
			
In all TigerStops from ver 5.0 on the scale is used to convert encoder counts to inches.
The math for this is y = mx + b.

m = scale
x = encoder counts
b = offset
y = position in inches

In the system now, offset always = 0, when you calibrate the TS, I set the count to the 
raw count so zero is always zero for encoder counts and inches.

So we have y = mx,  inches = scale * encoder counts.  This makes calibration & scaling
very easy.

NewScale = OldScale * Position / Realposition

old scale = 2.362
Position  = 100.00
Real pos  = 99.9

NewScale = 2.362 * 100 / 99.9
NewScale = 2.36436

So what falls out of this is 

1.   Calibrate as near zero as you can, sets the offset to zero
2.  move to the longest length you can measure (Longer really is better)
3.	calculate the new scale from above
4.  enter the new scale value
5.  DONE