var builder = DistributedApplication.CreateBuilder(args);

var api = builder.AddProject<Projects.Wegrep_Api>("api");

var app = builder.AddJavaScriptApp("app", "../Wegrep.App", "dev")
    .WaitFor(api)
    .WithReference(api)
    .WithEnvironment("NEXT_PUBLIC_API_BASE_URL", api.GetEndpoint("https"))
    .WithHttpEndpoint(env: "PORT")
    .WithExternalHttpEndpoints();

api.WithEnvironment("APP_URL", app.GetEndpoint("http"));

builder.Build().Run();
