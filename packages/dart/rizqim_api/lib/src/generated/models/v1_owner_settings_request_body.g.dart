// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerSettingsRequestBody _$V1OwnerSettingsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerSettingsRequestBody(
  isOpen: json['is_open'] as bool,
  paymentSettings: PaymentSettings2.fromJson(
    json['payment_settings'] as Map<String, dynamic>,
  ),
  name: json['name'] as String?,
  timezone: json['timezone'] as String?,
  logo: json['logo'] as String?,
  banner: json['banner'] as String?,
  description: json['description'] as String?,
  phone: json['phone'] as String?,
  address: json['address'] as String?,
  locationUrl: json['location_url'] as String?,
  minOrderAmount: json['min_order_amount'] as num?,
  tin: json['tin'] as String?,
  taxRegime: json['tax_regime'] == null
      ? null
      : TaxRegime.fromJson(json['tax_regime'] as String?),
  fiscalSettings: json['fiscal_settings'] == null
      ? null
      : FiscalSettings5.fromJson(
          json['fiscal_settings'] as Map<String, dynamic>,
        ),
);

Map<String, dynamic> _$V1OwnerSettingsRequestBodyToJson(
  V1OwnerSettingsRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'timezone': instance.timezone,
  'logo': instance.logo,
  'banner': instance.banner,
  'description': instance.description,
  'phone': instance.phone,
  'address': instance.address,
  'location_url': instance.locationUrl,
  'is_open': instance.isOpen,
  'min_order_amount': instance.minOrderAmount,
  'tin': instance.tin,
  'tax_regime': instance.taxRegime,
  'fiscal_settings': instance.fiscalSettings,
  'payment_settings': instance.paymentSettings,
};
