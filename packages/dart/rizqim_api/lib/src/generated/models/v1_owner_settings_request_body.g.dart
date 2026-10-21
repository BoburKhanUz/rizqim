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
  'payment_settings': instance.paymentSettings,
};
