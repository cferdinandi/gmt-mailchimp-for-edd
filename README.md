# GMT MailChimp for EDD

Automatically add people who purchase specific downloads to your MailChimp list. You can also optionally add them to interest groups.

[Download GMT MailChimp for EDD](https://github.com/cferdinandi/gmt-mailchimp-for-edd/archive/master.zip)



## Getting Started

Getting started with GMT MailChimp for EDD is as simple as installing a plugin:

1. Upload the `gmt-mailchimp-for-edd` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.

And that's it, you're done. Nice work!

It's recommended that you also install the [GitHub Updater plugin](https://github.com/afragen/github-updater) to get automatic updates.



## Adding a buyer to a MailChimp list

In your download, under `MailChimp`:

1. Check `Subscribe buyers to your list`.
2. Add a `List ID`.
3. If you want subscribers to receive the double opt-in email, check `Enable double opt-in`.
4. Check off any desired interest groups.
5. Click `Publish` or `Update`.


## Updating subscriptions on refunds and subscription cancellations

When someone gets a refund or unsubscribes for a recurring payment, they will be removed from any of the groups. You can subscribe them to new groups *or* keep them in their existing ones by checking any desired groups under `On Cancel or Refund`.



## License

The code is available under the [GPLv3 License](LICENSE.md).