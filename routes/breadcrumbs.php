<?php

use App\Models\User;
use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;
use Spatie\Permission\Models\Role;

// Home
Breadcrumbs::for('home', function (BreadcrumbTrail $trail) {
    $trail->push('Home', route('admin.dashboard'));
});

Breadcrumbs::for('common', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

// Home > Dashboard
Breadcrumbs::for('dashboard', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.dashboard'));
});

//Master
Breadcrumbs::for('master.scheme.type', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.scheme-type.index'));
});


//System Configuration
Breadcrumbs::for('systemConfiguration.systemSettings', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.systemConfiguration.systemSettings.get'));
});

Breadcrumbs::for('systemConfiguration.appVersionControl.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('systemConfiguration.appVersionControl.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Version List', route('admin.systemConfiguration.appVersionControl.list'));
    $trail->push(getPageTitle(), request()->route()->getName());
});
//End System Configuration



Breadcrumbs::for('master.country', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.country.index'));
});

Breadcrumbs::for('master.state', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.state.index'));
});

Breadcrumbs::for('master.city', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.city.index'));
});

Breadcrumbs::for('master.bank', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.bank.index'));
});

Breadcrumbs::for('master.bank-account-type', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.bank-account-type.index'));
});

Breadcrumbs::for('master.family-relation', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.family-relation.index'));
});

Breadcrumbs::for('master.investor-type', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.investor-type.index'));
});

Breadcrumbs::for('master.startup-round-type', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.startup-round-type.index'));
});

Breadcrumbs::for('master.instrument-type', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.instrument-type.index'));
});

Breadcrumbs::for('master.industry', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.industry.index'));
});

Breadcrumbs::for('master.sector', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.sector.index'));
});
Breadcrumbs::for('master.project', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.project.index'));
});


Breadcrumbs::for('master.social-media', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.social-media.index'));
});

Breadcrumbs::for('cms.blog', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.cms.blog.index'));
});
Breadcrumbs::for('cms.blog.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Blogs', route('admin.cms.blog.index'));
    $trail->push(getPageTitle(), route('admin.cms.blog.create'));
});
Breadcrumbs::for('cms.blog.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Blogs', route('admin.cms.blog.index'));
    $trail->push(getPageTitle(), route('admin.cms.blog.edit', 'blog'));
});

Breadcrumbs::for('cms.faqs', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.cms.faqs.index'));
});

Breadcrumbs::for('cms.avtar', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.cms.avtar.index'));
});
Breadcrumbs::for('cms.avtar.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Avtars', route('admin.cms.avtar.index'));
    $trail->push(getPageTitle(), route('admin.cms.avtar.create'));
});
Breadcrumbs::for('cms.avtar.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Avtars', route('admin.cms.avtar.index'));
    $trail->push(getPageTitle(), route('admin.cms.avtar.edit', 'avtar'));
});

Breadcrumbs::for('findcml.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.master.findcml.list'));
});
Breadcrumbs::for('findcml.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('findcml', route('admin.master.findcml.list'));
    $trail->push(getPageTitle(), route('admin.master.findcml.create'));
});
Breadcrumbs::for('findcml.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('findcml', route('admin.master.findcml.list'));
    $trail->push(getPageTitle(), route('admin.master.findcml.edit', 'findcml'));
});

Breadcrumbs::for('cms.media.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle());
});
Breadcrumbs::for('cms.media.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Media', route('admin.cms.media.list'));
    $trail->push(getPageTitle());
});
Breadcrumbs::for('cms.website-social-media', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.cms.website-social-media.index'));
});

Breadcrumbs::for('cms.pages', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.cms.pages.index'));
});
Breadcrumbs::for('cms.pages.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Pages', route('admin.cms.pages.index'));
    $trail->push(getPageTitle(), route('admin.cms.pages.edit', ''));
});

Breadcrumbs::for('cms.manage-info-icon', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.cms.manage-info-icon.index'));
});

Breadcrumbs::for('cms.manage-info-icon.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Manage Info Icon', route('admin.cms.manage-info-icon.index'));
    $trail->push(getPageTitle(), route('admin.cms.manage-info-icon.edit', ''));
});


Breadcrumbs::for('investor.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.investor.create'));
});
Breadcrumbs::for('investor.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});
Breadcrumbs::for('investor.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.investor.edit', ''));
});
Breadcrumbs::for('investor.manualkyc', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.manualkyc.pending'));
});
Breadcrumbs::for('investor.documents', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});


Breadcrumbs::for('wealthmanager.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Wealth Manager List', route('admin.partner.wealthmanager.list'));
    $trail->push(getPageTitle(), route('admin.partner.wealthmanager.create'));
});
Breadcrumbs::for('wealthmanager.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Wealth Manager List', route('admin.partner.wealthmanager.list'));
    $trail->push(getPageTitle(), route('admin.partner.wealthmanager.edit', 'wealthmanager'));
});
Breadcrumbs::for('wealthmanager.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});
Breadcrumbs::for('distributor.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Distributor List', route('admin.partner.distributor.list'));
    $trail->push(getPageTitle(), route('admin.partner.distributor.create'));
});
Breadcrumbs::for('distributor.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Distributor List', route('admin.partner.distributor.list'));
    $trail->push(getPageTitle(), route('admin.partner.distributor.edit', 'distributor'));
});
Breadcrumbs::for('distributor.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});
Breadcrumbs::for('institution.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Institution List', route('admin.partner.institution.list'));
    $trail->push(getPageTitle(), route('admin.partner.institution.create'));
});
Breadcrumbs::for('institution.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Institution List', route('admin.partner.institution.list'));
    $trail->push(getPageTitle(), route('admin.partner.institution.edit', 'institution'));
});
Breadcrumbs::for('institution.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});
Breadcrumbs::for('retailer.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Retailer List', route('admin.partner.retailers.list'));
    $trail->push(getPageTitle(), route('admin.partner.retailers.create'));
});
Breadcrumbs::for('retailer.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Retailer List', route('admin.partner.retailers.list'));
    $trail->push(getPageTitle(), route('admin.partner.retailers.edit', 'retailers'));
});
Breadcrumbs::for('retailer.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});





Breadcrumbs::for('relationalManager.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Relational Manager List', route('admin.partner.relationalManager.list'));
    $trail->push(getPageTitle(), route('admin.partner.relationalManager.create'));
});
Breadcrumbs::for('relationalManager.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Relational Manager List', route('admin.partner.relationalManager.list'));
    $trail->push(getPageTitle(), route('admin.partner.relationalManager.edit', 'relationalManager'));
});
Breadcrumbs::for('relationalManager.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});


Breadcrumbs::for('primarytransactions.pending', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Pending List');
});

Breadcrumbs::for('primarytransactions.completed', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Completed List');
});

Breadcrumbs::for('secondarytransactions.pending', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Pending List');
});

Breadcrumbs::for('secondarytransactions.completed', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Completed List');
});

Breadcrumbs::for('secondarySellRequest.pending', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Pending Sell Requests');
});

Breadcrumbs::for('secondarySellRequest.inProgress', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('In Progress Sell Requests');
});

Breadcrumbs::for('secondarySellRequest.completed', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Completed Sell Requests');
});

Breadcrumbs::for('companyEnquiry.pending', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Pending Company Enquiries');
});

Breadcrumbs::for('companyEnquiry.completed', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Completed Company Enquiries');
});

Breadcrumbs::for('notification.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('feedback.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('activeToday.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Reports');
    $trail->push('Application Logs');
    if (request()->routeIs('admin.reports.applogs.distributer.active-today.*')) {
        $trail->push('Distributor');
    } else {
        $trail->push('Investor');
    }
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('reports.resourceBilling.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('reports.resourceBilling.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Resource List', route('admin.reports.resourceBilling.list'));
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('contact.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

//Startup
Breadcrumbs::for('startup.manage', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Startup List', route('admin.startup.list'));
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('startup.update', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('startup.mis', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

//coupon
Breadcrumbs::for('coupon.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Coupon List', route('admin.coupon.list'));
    $trail->push(getPageTitle(), route('admin.coupon.create'));
});

Breadcrumbs::for('coupon.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

// Company
Breadcrumbs::for('company.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Company List', route('admin.company.list'));
    $trail->push(getPageTitle(), route('admin.company.create'));
});

Breadcrumbs::for('company.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('company.pendingSeller', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Company List', route('admin.company.list'));
    $trail->push(getPageTitle(), route('admin.company.pendingSeller'));
});

Breadcrumbs::for('bse-holiday.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('bse-holiday.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('BSE Holidays', route('admin.bse-holiday.index'));
    $trail->push(getPageTitle(), route('admin.bse-holiday.create'));
});

Breadcrumbs::for('bse-holiday.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('BSE Holidays', route('admin.bse-holiday.index'));
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('company-deals.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('company-deals.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Company Deals', route('admin.company-deals.index'));
    $trail->push(getPageTitle(), route('admin.company-deals.create'));
});

Breadcrumbs::for('company-deals.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Company Deals', route('admin.company-deals.index'));
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('preipotransactions.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.preipotransaction.pending'));
});

Breadcrumbs::for('preiposeller.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Seller List', route('admin.preiposeller.list'));
    $trail->push(getPageTitle(), route('admin.preiposeller.create'));
});

Breadcrumbs::for('preiposeller.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});
//portfolio 

Breadcrumbs::for('portfolioInsights.startupPortfolio.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.portfolioInsights.startupPortfolio.list'));
});

Breadcrumbs::for('portfolioInsights.startupPortfolio.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Portfolio List', route('admin.portfolioInsights.startupPortfolio.list'));
    $trail->push(getPageTitle(), route('admin.portfolioInsights.startupPortfolio.create'));
});

Breadcrumbs::for('mis.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), route('admin.startup.mis.pending'));
});

Breadcrumbs::for('mis.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('MIS List', route('admin.startup.mis.pending'));
    $trail->push(getPageTitle(), route('admin.startup.mis.create'));
});

Breadcrumbs::for('notification.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('manage-startup.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('mgt14.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('offerrequest.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('pas3.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('secondary-payment-receipt.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('primary-payment-receipt.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('broadcast.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('broadcast.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Whatsapp Broadcast', route('admin.broadcast.whatsapp.list'));
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('broadcast_notification.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});

Breadcrumbs::for('broadcast_notification.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Notification Broadcast', route('admin.broadcast.pushNotification.list'));
    $trail->push(getPageTitle(), request()->route()->getName());
});
// Breadcrumbs::for('uploadDocument.create', function (BreadcrumbTrail $trail) {
//     $trail->parent('home');
//     $trail->push('Upload Document', route('admin.broadcast.whatsapp.list'));
//     $trail->push(getPageTitle(), request()->route()->getName());
// });



// // Home > Dashboard > User Management
// Breadcrumbs::for('user-management.index', function (BreadcrumbTrail $trail) {
//     $trail->parent('dashboard');
//     $trail->push('User Management', route('admin.user-management.users.index'));
// });

// // Home > Dashboard > User Management > Users
// Breadcrumbs::for('user-management.users.index', function (BreadcrumbTrail $trail) {
//     $trail->parent('user-management.index');
//     $trail->push('Users', route('admin.user-management.users.index'));
// });

// // Home > Dashboard > User Management > Users > [User]
// Breadcrumbs::for('user-management.users.show', function (BreadcrumbTrail $trail, User $user) {
//     $trail->parent('user-management.users.index');
//     $trail->push(ucwords($user->name), route('user-management.users.show', $user));
// });

// // Home > Dashboard > User Management > Roles
// Breadcrumbs::for('user-management.roles.index', function (BreadcrumbTrail $trail) {
//     $trail->parent('user-management.index');
//     $trail->push('Roles', route('admin.user-management.roles.index'));
// });

// // Home > Dashboard > User Management > Roles > [Role]
// Breadcrumbs::for('user-management.roles.show', function (BreadcrumbTrail $trail, Role $role) {
//     $trail->parent('user-management.roles.index');
//     $trail->push(ucwords($role->name), route('user-management.roles.show', $role));
// });

// // Home > Dashboard > User Management > Permission
// Breadcrumbs::for('user-management.permissions.index', function (BreadcrumbTrail $trail) {
//     $trail->parent('user-management.index');
//     $trail->push('Permissions', route('admin.user-management.permissions.index'));
// });

Breadcrumbs::for('manager.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Users List', route('admin.manager.list'));
    $trail->push(getPageTitle(), route('admin.manager.create'));
});
Breadcrumbs::for('manager.list', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(getPageTitle(), request()->route()->getName());
});
Breadcrumbs::for('manager.edit', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Users List', route('admin.manager.list'));
    $trail->push(getPageTitle(), route('admin.manager.edit', ''));
});
