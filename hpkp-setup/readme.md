
# HPKP Setup

Otherwise known as "HTTP Public Key Pinning".

There are other resources that will give you more information about what it does and why:

* [Scott Helme](https://scotthelme.co.uk/hpkp-http-public-key-pinning/)
* [Tim Taubert](https://timtaubert.de/blog/2014/10/http-public-key-pinning-explained/)
* [MDN](https://developer.mozilla.org/en/docs/Web/Security/Public_Key_Pinning)
* [Wikipedia](https://en.wikipedia.org/wiki/HTTP_Public_Key_Pinning)

In general, most websites should **not** use key pinning.

It is far to easy to break your own website, where your time would be better spent adding a [CSP Header](https://developer.mozilla.org/en-US/docs/Web/Security/CSP) (Content Security Policy), and if you have one already, try removing the `unsafe-inline` (which you almost certainly have in there).

The only time HPKP is useful is when you are a **big** target (e.g. a Bank), where it's likely that an attacker will give up finding XSS exploits and other vulnerabilities, and instead attempt to get a valid HTTPS certificate from a trusted CA (Certificate Authority), *and* be able to Man-In-The-Middle the connection for your customers (neither of these are easy).

Generally speaking, do you have more than one person managing your sever infrastructure?

If so, or you want to ignore this advice, the process I follow for implementing HPKP is below...

---

1. Generate two Public/Private key-pairs on a computer that is **not** the Live server.

		openssl genrsa -out "example.com.key" 2048;

		openssl genrsa -out "example.com.backup1.key" 2048;

	This second key-pair is a backup, and should probably use "-passout stdin" to protect the key while it's in storage.

2. Generate hashes for both of the Public keys. These will be used in the HPKP header later.

		openssl rsa -in "example.com.key"         -outform der -pubout | openssl dgst -sha256 -binary | openssl enc -base64;

		openssl rsa -in "example.com.backup1.key" -outform der -pubout | openssl dgst -sha256 -binary | openssl enc -base64;

3. Store the second (backup1) key-pair somewhere safe, probably somewhere encrypted like a password manager. Then securely delete the original (the one outside of the backup location):

		shred -u example.com.backup1.key;

	This backup key (now in a safe location) won't expire, as it's just a key-pair. It just needs to be ready for when you need to get your next certificate.

4. Generate a single CSR (Certificate Signing Request) for the first key-pair:

		openssl req -new -subj "/C=GB/ST=Area/L=Town/O=Company/CN=example.com" -key "example.com.key" -out "example.com.csr";

5. Send this CSR to the CA (Certificate Authority), and go though the dance to prove you own the domain. They will give you back a single certificate that will typically expire within a year or two.

6. On the Live server, upload and setup the first key-pair (and its certificate).

	Note: **Only** the first key-pair has been uploaded to the server.

7. Now you can add the `Public-Key-Pins` header, using the two hashes you created in step 2.

		Public-Key-Pins: pin-sha256="XXX"; pin-sha256="XXX"; max-age=2592000; includeSubDomains; report-uri="XXX"

	Note: **Do not** set the max-age too far in the future, 30 days should be about right.

8. Time passes... probably just under a year (if waiting for a certificate to expire), or maybe sooner if you find that your server has been compromised and you need to replace the key-pair and certificate.

9. Create a new CSR (Certificate Signing Request) using the "backup1" key-pair, and get a new certificate from your CA.

10. Generate a new backup key-pair (backup2), get its hash, and store it in a safe place (again, **not** on the Live server).

11. Replace your old certificate and old key-pair, update the `Public-Key-Pins` header to remove the old hash, and add the new "backup2" key-pair.

Note: If the strength of the keys is ever deemed to be too weak (as was the case with 1024 bit keys), then you **must** generate new backup keys, and update the `Public-Key-Pins` header as soon as possible,

---

As an aside, you can also extract the Public key from your CSR:

	openssl req -in "example.com.csr" -pubkey -noout | openssl rsa -pubin -outform der | openssl dgst -sha256 -binary | openssl enc -base64;

And your certificate:

	openssl x509 -in "example.com.crt" -pubkey -noout | openssl rsa -pubin -outform der | openssl dgst -sha256 -binary | openssl enc -base64;

Or get it from a website directly:

	openssl s_client -connect www.thriveapproach.co.uk:443 | openssl x509 -pubkey -noout | openssl rsa -pubin -outform der | openssl dgst -sha256 -binary | openssl enc -base64

---

It's also possible to pin to your Certificate Authority, but I personally do not believe this is a good idea.

For example, they may change their root or intermediate certificates (e.g. when it comes to your certificates renewal, or if they are compromised), and its possible for them (or a different CA who use their root certificate) to issue a duplicate certificate and give it to someone else.
