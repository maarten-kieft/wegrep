using Wegrep.Api.Clients;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddControllers();
builder.Services.AddHttpClient();
builder.Services.AddTransient<IHockeyDataClient, HockeyDataClient>();
builder.Services.Configure<HockeyDataClientOptions>(builder.Configuration.GetSection(HockeyDataClientOptions.SectionName));

var app = builder.Build();
app.UseHttpsRedirection();
app.UseAuthorization();
app.MapControllers();
app.UseCors(p => p
    .AllowAnyHeader()
    .AllowAnyMethod()
    .AllowCredentials()
    .WithOrigins(builder.Configuration["APP_URL"] ?? "*")
);
await app.RunAsync();
