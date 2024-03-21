<?php 
namespace App\Libraries;

class Coords2Color{

    public function getColor( string $claster, float $lat, float $lon ){
        $clasterBoundaries=getenv("location.{$claster}");
        $claster=json_decode("[$clasterBoundaries]");
        $offsetX=$claster[0][0];
        $offsetY=$claster[0][1];
        $radiusX=($claster[1][0]-$offsetX)/2;
        $radiusY=($claster[1][1]-$offsetY)/2;

        /**
         * normalizing x and y so it will vary from -1 to 1
         */
        $x=($lon-$offsetX-$radiusX)/$radiusX;
        $y=($lat-$offsetY-$radiusY)/$radiusY;

        $radius=($x**2+$y**2)**0.5;//~saturation
        $angle=rad2deg(atan2($y,$x));
        if( $angle<0 ){
          $angle+=360;
        }
        $saturation=$radius;
        if($radius>1){
          $saturation=1;
            //continue;
        }
        $hue=$angle/360;
        $lum=min(1,1.15-$saturation)**0.7;
        list($red,$green,$blue)=$this->HSVtoRGB([$hue,$saturation,$lum]);
        //pl("$x,$y || $radius $angle");
        return sprintf("#%02x%02x%02x", $red,$green,$blue);
    }

    private function HSVtoRGB( array $hsv ) {
        list($H,$S,$V) = $hsv;
        //1
        $H *= 6;
        //2
        $I = floor($H);
        $F = $H - $I;
        //3
        $M = $V * (1 - $S);
        $N = $V * (1 - $S * $F);
        $K = $V * (1 - $S * (1 - $F));
        //4
        switch ($I) {
            case 0:
                list($R,$G,$B) = array($V,$K,$M);
                break;
            case 1:
                list($R,$G,$B) = array($N,$V,$M);
                break;
            case 2:
                list($R,$G,$B) = array($M,$V,$K);
                break;
            case 3:
                list($R,$G,$B) = array($M,$N,$V);
                break;
            case 4:
                list($R,$G,$B) = array($K,$M,$V);
                break;
            case 5:
            case 6: //for when $H=1 is given
                list($R,$G,$B) = array($V,$M,$N);
                break;
        }
        return [ round(255*$R), round(255*$G), round(255*$B) ];
    }
}