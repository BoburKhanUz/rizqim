// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data71.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data71 _$Data71FromJson(Map<String, dynamic> json) => Data71(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  timezone: json['timezone'] as String,
  logo: json['logo'] as String?,
  logoUrl: json['logo_url'] as String?,
  banner: json['banner'] as String?,
  bannerUrl: json['banner_url'] as String?,
  description: json['description'] as String?,
  phone: json['phone'] as String?,
  address: json['address'] as String?,
  locationUrl: json['location_url'] as String?,
  isOpen: json['is_open'] as bool,
  minOrderAmount: json['min_order_amount'] as num,
  paymentSettings: PaymentSettings.fromJson(
    json['payment_settings'] as Map<String, dynamic>,
  ),
  paymentAccountType: PaymentAccountType.fromJson(
    json['payment_account_type'] as String,
  ),
  status: StoreStatus.fromJson(json['status'] as String),
  isOrderLocked: json['is_order_locked'] as bool,
  allowedMethods: (json['allowed_methods'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data71ToJson(Data71 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'timezone': instance.timezone,
  'logo': instance.logo,
  'logo_url': instance.logoUrl,
  'banner': instance.banner,
  'banner_url': instance.bannerUrl,
  'description': instance.description,
  'phone': instance.phone,
  'address': instance.address,
  'location_url': instance.locationUrl,
  'is_open': instance.isOpen,
  'min_order_amount': instance.minOrderAmount,
  'payment_settings': instance.paymentSettings,
  'payment_account_type': instance.paymentAccountType,
  'status': instance.status,
  'is_order_locked': instance.isOrderLocked,
  'allowed_methods': instance.allowedMethods,
};
