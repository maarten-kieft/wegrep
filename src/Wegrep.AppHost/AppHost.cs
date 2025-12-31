var builder = DistributedApplication.CreateBuilder(args);

var api = builder.AddProject<Projects.Wegrep_Api>("api");

builder.Build().Run();
