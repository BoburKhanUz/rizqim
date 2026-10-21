// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data69.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data69 _$Data69FromJson(Map<String, dynamic> json) => Data69(
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
  paymentSettings: json['payment_settings'] as String,
  paymentAccountType: json['payment_account_type'] as String,
  status: json['status'],
  isOrderLocked: json['is_order_locked'] as bool,
  allowedMethods: json['allowed_methods'] as List<dynamic>,
);

Map<String, dynamic> _$Data69ToJson(Data69 instance) => <String, dynamic>{
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
