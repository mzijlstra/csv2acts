<?php
require "passwd.php";

if (getenv('USER') !== "root") {
	die("Script needs to run as root (which you are not)\n");
}

if (count ($argv) !== 2 || !preg_match("/.*\.csv/i" ,$argv[1])) {
	die("Script expects a single argument that is the name of a .csv file\n");
}


$lines = file($argv[1]);
foreach($lines as $line) {
	list($sid, $first, $last, $email) = str_getcsv($line);

	# lines that do not start with a studentId are ignored
	if (preg_match("/^(98|10)\d{4}$/", $sid)) {

		# in /etc/passwd set the comment field to:
		# full name,studentId,,,email
		$comment = "$first $last,$sid,,,$email";
		# cleanup lastname
		$last = preg_replace("/[ ']/", "", $last);
		#$username = strtolower($first[0] . $last);
        $username = $sid;

		# students don't get their own group, but are in users and jail
		exec("useradd -m -c '$comment' -N -g users -G jail $username");

		# set passphrase
		$pass = passwd();
        $result = 0;
		exec("echo '$pass\n$pass\n' | passwd $username > /dev/null 2>&1", $result);

		# set home dir permissions so that jail works & logs are not endangered
		exec("chown root:root /home/$username /home/$username/log");
		exec("chmod 755 /home/$username");
		exec("chmod 755 /home/$username/log");

		# set the users' quote to 50MB (1 quota block is 1K)
		exec("quotatool -u $username -b -l 51200 /");
		
		# create custom welcome message
		$message = 
"Dear $first $last,

This is an automated email to notify you that the following account has been created for you on sftp://mumstudents.org

user: $username
pass: $pass

Please note that both the username and password are case sensitive, and that the password includes spaces and at least one punctuation mark.

You can use an sftp client like FileZilla to log into your account, your course instructor will demonstrate how to do this during the first couple of days of your course.

Please do not reply to this email, instead direct any questions about your account to your instructor.

Best of luck,

Mumstudents.org Automated Account Creator

P.S. Please let professor Michael Zijlstra mzijlstra@mum.edu know if you want to try and break the security on mumstudents.org. I'm always happy to work with students to improve security. I'm not so happy to find out after the fact if a machine has been compromised (cracking is a crime, and there are consequences).
";

		#email the user about his newly created account
		$headers ='FROM: "Mumstudents.org Accounts" <accounts@mumstudents.org>';
		mail($email, "mumstudents.org account", $message, $headers);
		echo "created account: $username\n";
	}
}

?>
