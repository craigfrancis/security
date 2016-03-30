
# Third-party iFrames

At the moment nearly all third party content is given *full* access to a webpage with a `script` tag, which means it can do pretty much anything it likes to the page.

This is really bad.

A possible solution is to use an `iframe` - but we can't at the moment, as they are too restrictive.

If we look at advertising as an example, which is our biggest third party problem (the current solution from the users point of view is to simply block them)...

---

Advertising companies will insist on being able to see the content on the page (so they can place "relevant" advertising), while not all of us are fans of this practice, this still needs to be possible to keep them happy. So we need a compromise.

What if we take the current @sandbox attribute, and add a new token that allows the iframed document to only read the textContent from the parent DOM (and maybe the window.location).

While the textContent may still contain sensitive information (e.g. the username for the logged in person), it will stop access to most data within the DOM, which the third party should not have access to (e.g. `csrf` tokens in hidden input fields).

And, with the main document only granting read-only access, website owners can trust that the advertising companies are not doing anything they shouldn't be, such as editing the content of the page, changing links, adding key press event handlers, etc.

	<iframe sandbox="allow-parent-text-content-read ...

---

By using @sandbox, we have just isolated the potentially malicious content to somewhere a bit safer :-)

Unfortunately advertising iframes will still need `allow-scripts` and `allow-top-navigation`.

Or maybe a tweaked version of `allow-top-navigation`, so that the navigation can only occur due to *user* input.

	<iframe sandbox="allow-user-top-navigation ...

Which, like the first popup blockers, will stop malicious code like:

	<script>top.location="https://www.example.com";</script>

This will address the problem where adverts are automatically redirecting users to a different webpage, or app store (a problem that [cultofmac.com](https://twitter.com/cultofmac/status/700905537077030913) recently had, where Google/DoubleClick let a bad set of adverts though, ones which also pulled in 300+ resources, 8MB of data, then automatically redirected to a porn site).

---

We also need to stop third party content from slowing down the webpage too much.

With it being in an iframe, the browser can already apply some restrictions, but as Ilya Grigorik has just suggested, we could take this even further with `cgroups` for the web:

https://www.igvita.com/2016/03/01/control-groups-cgroups-for-the-web/

	<iframe cgroup="priority medium;" ...

---

Now most third party content does change its content (kind of the point), so the iframe cannot always be a fixed size (as it is at the moment).

Hopefully we can finally implement a feature request from 2001, allowing the iframe height to change automatically (only Google Chrome is holding this feature back at the moment):

	iframe {
		height: max-content;
	}

Note, the third party will need to add a header, so the iframe can auto resize:

	Expose-Height-Cross-Origin: 1;

https://github.com/craigfrancis/iframe-height

---

One other change we might need to see is for iOS to stop blocking third party cookies within iframes.

While I really like the approach that Apple took, it does cause some problems.

For example Disqus isn't always able to keep the person logged in, same goes with 3D-(in)Secure credit card re-verification, and websites where they outsource certain elements of their website (e.g. the checkout process).

---

So in summary, by using something like:

	<iframe sandbox="allow-parent-text-content-read allow-user-top-navigation allow-scripts" cgroup="priority medium;" src="https://www.example.com/ads.js" />

	iframe {
		width: 300px;
		height: max-content; /* or maybe a fixed height, if you prefer */
	}

We can give the third party somewhere safe for their JavaScript to run, and website owners can explicitly define where that content is displayed, without worrying about all of the security, performance, and user experience problems we currently have.

And this is starting from strong foundations, as iframe's already exist, and already have a good set of restrictions - which is much better than trying to apply restrictions on a system that is currently open for abuse (like script tags).

It will also mean that a Content Security Policy (CSP) will be easier to write for website owners (while the `unsafe-dynamic` suggestion from Mike West will help here, CSP will be much easier to implement if the third party content was locked behind a single iframe URL).

---

## More examples:

Comment Areas

https://disqus.com/admin/create/

https://developers.facebook.com/docs/plugins/comments

Calendars

https://support.google.com/calendar/answer/41207

Timelines

https://dev.twitter.com/web/embedded-timelines

Maps

https://developers.google.com/maps/documentation/javascript/

https://www.bingmapsportal.com/isdk/ajaxv7

Like Buttons

https://dev.twitter.com/web/tweet-button/javascript-create

https://developers.facebook.com/docs/plugins/like-button

Although with "Like Buttons", we might need to think about how they can display content that doesn't really fit the height changing solution - maybe we could use the new `<dialog>` element, and a variation of the sandbox `allow-modals`, perhaps a safer `allow-dialog-modals`?