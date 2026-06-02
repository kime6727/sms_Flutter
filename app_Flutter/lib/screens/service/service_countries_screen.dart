import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../../core/i18n/app_localizations.dart';
import '../../providers/service_provider.dart';
import '../../providers/auth_provider.dart';
import '../../models/service_country_model.dart';
import '../../widgets/common_widgets.dart';

class ServiceCountriesScreen extends StatefulWidget {
  final int serviceId;
  const ServiceCountriesScreen({super.key, required this.serviceId});

  @override
  State<ServiceCountriesScreen> createState() => _ServiceCountriesScreenState();
}

class _ServiceCountriesScreenState extends State<ServiceCountriesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ServiceProvider>().loadPublishedServiceCountries(
            serviceId: widget.serviceId,
          );
    });
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final serviceProvider = context.watch<ServiceProvider>();
    final countries = serviceProvider.getCountriesForService(widget.serviceId)
      ..sort((a, b) => a.price.compareTo(b.price));

    return Scaffold(
      appBar: AppBar(
        title: Text(loc.translate('select_country')),
      ),
      body: serviceProvider.isLoading
          ? const Center(child: CircularProgressIndicator())
          : countries.isEmpty
              ? EmptyWidget(
                  icon: Icons.public_outlined,
                  message: loc.translate('no_countries'),
                  subtitle: loc.translate('no_countries_subtitle'),
                  onAction: () => context.pop(),
                  actionIcon: Icons.arrow_back,
                )
              : Scrollbar(
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: countries.length,
                    itemBuilder: (context, index) {
                      final sc = countries[index];
                      return _CountryCard(serviceCountry: sc);
                    },
                  ),
                ),
    );
  }
}

class _CountryCard extends StatelessWidget {
  final ServiceCountryModel serviceCountry;
  const _CountryCard({required this.serviceCountry});

  int _getDisplayPrice() {
    // 优先使用 API 返回的 pricePoints（已包含后端系数计算）
    if (serviceCountry.pricePoints != null && serviceCountry.pricePoints! > 0) {
      return serviceCountry.pricePoints!;
    }
    // 仅在 pricePoints 为空时使用硬编码 fallback（不应发生，因为现在调用 published 接口）
    return (serviceCountry.price * 100 * 4).ceil();
  }

  String _generateFakeNumber() {
    final phoneCode = serviceCountry.countryPhoneCode ?? '';
    final rng = serviceCountry.id % 1000;
    final suffix = (1000 + (rng * 7 + serviceCountry.countryId) % 9000).toString();
    return '+$phoneCode **** $suffix';
  }

  @override
  Widget build(BuildContext context) {
    final loc = context.loc;
    final authProvider = context.watch<AuthProvider>();
    final fakeNumber = _generateFakeNumber();
    final displayPrice = _getDisplayPrice();

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: authProvider.isAuthenticated
            ? () {
                context.push(
                  '/home/service/${serviceCountry.serviceId}/countries/purchase?country_id=${serviceCountry.countryId}&service_name=${Uri.encodeComponent(serviceCountry.serviceDisplayName)}&country_name=${Uri.encodeComponent(serviceCountry.countryDisplayName)}',
                );
              }
            : null,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              // Flag
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surfaceVariant,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: serviceCountry.localCountryFlag.isNotEmpty
                    ? Image.network(
                        serviceCountry.localCountryFlag,
                        fit: BoxFit.cover,
                        frameBuilder: (context, child, frame, wasSynchronouslyLoaded) {
                          if (frame == null) {
                            return const Center(child: CircularProgressIndicator(strokeWidth: 2));
                          }
                          return child;
                        },
                        errorBuilder: (context, error, stackTrace) {
                          return Center(
                            child: Text(
                              serviceCountry.countryFlagEmoji ?? '🌍',
                              style: const TextStyle(fontSize: 28),
                            ),
                          );
                        },
                      )
                    : Center(
                        child: Text(
                          serviceCountry.countryFlagEmoji ?? '🌍',
                          style: const TextStyle(fontSize: 28),
                        ),
                      ),
                ),
              ),
              const SizedBox(width: 12),
              // Country name and phone number
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      serviceCountry.countryDisplayName,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      fakeNumber,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                        fontSize: 13,
                        fontFamily: 'monospace',
                      ),
                    ),
                  ],
                ),
              ),
              // Price and buy button
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Image.asset(
                        'assets/icons/jifen.webp',
                        width: 16,
                        height: 16,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '$displayPrice',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 16,
                          color: Color(0xFF6366F1),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ElevatedButton(
                    onPressed: authProvider.isAuthenticated
                        ? () {
                            context.push(
                              '/home/service/${serviceCountry.serviceId}/countries/purchase?country_id=${serviceCountry.countryId}&service_name=${Uri.encodeComponent(serviceCountry.serviceDisplayName)}&country_name=${Uri.encodeComponent(serviceCountry.countryDisplayName)}',
                            );
                          }
                        : null,
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                    ),
                    child: Text(
                      loc.translate('buy'),
                      style: const TextStyle(fontSize: 13),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
