var DocsMenuItem = function() {

    var self = this;

    self.name = ko.observable();
    self.path = ko.observable();
    self.active = ko.observable(false);
    self.url = ko.observable();
    self.title = ko.computed(function() {

        var filename = self.name().substr(0, self.name().lastIndexOf('.')) || self.name(); // remove extension
        filename = filename.replace('-', ' ');
        filename = filename.replace(/[0-9.$-]/g, "");
        return filename.charAt(0).toUpperCase() + filename.slice(1);
    });
};

var Docs = function(marked, githubApiUrl) {

    var self = this;

    self.content = ko.observable();
    self.menuItems = ko.observableArray();
    self.contentHeader = ko.observable();
    self.visibleError = ko.observable(false);
    self.contentLoading = ko.observable(false);

    self.clickHideError = function() {
        self.visibleError(false);
    };

    self.clickMenuItem = function(menuItem) {

        self.visibleError(false);
        self.contentLoading(true);

        $.ajax({
            url: menuItem.url(),
            headers: {
                Accept : "application/vnd.github.v3.raw"
            }
        }).fail(function() {
            self.visibleError(true);
        }).done(function(response) {
            self.contentHeader(menuItem.title());
            self.content(marked(response));
        }).always(function(){
            self.contentLoading(false);
        });

        return false;
    };

    self.loadMenu = function() {

        $.ajax({
            url: githubApiUrl + "/contents"
        }).fail(function() {
            self.visibleError(true);
        }).done(function(response) {

            var mapping = {
                create: function(options) {

                    if (options.data.type === "file") {
                        var model = new DocsMenuItem();
                        ko.mapping.fromJS(options.data, {}, model);
                        return model;
                    }
                }
            };
            ko.mapping.fromJS(response, mapping, self.menuItems);

        });

    };

    self.loadMenu();
};

$(function() {

    $('#docs').each(function() {
        ko.applyBindings(new Docs(marked, "https://api.github.com/repos/bauer01/unimapper-docs"), this);
    });

});