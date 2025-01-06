 // *** print out 10 random numers in the range from 0 to 10 ***
 include "qrbg.php";
 $gen = new QRBG("BillWill@catlover.com","-~!,X3z*9i#:xZ2");  // instanciate QRBG object
                                    // with username, password (mandatory)
 $gen->setCachesize(10);            // set cache size to 10
 for ($i=0 ; $i<10 ; $i++) {
   $t=$gen->rand(30);               // get a random number between 0 and 30
   if ($t===false)                  // error check. The "===" is important!
     die ($gen->errstr()."\n");     // print out error message
   echo "$t\n";
 }
