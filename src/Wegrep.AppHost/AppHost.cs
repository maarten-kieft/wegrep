var builder = DistributedApplication.CreateBuilder(args);

var api = builder.AddProject<Projects.Wegrep_Api>("api");

var app = builder.AddJavaScriptApp("app", "../Wegrep.App", "dev")
    .WaitFor(api)
    .WithReference(api)
    .WithHttpEndpoint(env: "PORT")
    .WithExternalHttpEndpoints();

builder.Build().Run();
