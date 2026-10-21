// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'branch11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Branch11 _$Branch11FromJson(Map<String, dynamic> json) => Branch11(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  address: json['address'] as String?,
  phone: json['phone'] as String?,
  timezone: json['timezone'] as String,
  businessDayStartsAt: json['business_day_starts_at'] as String,
  businessDate: json['business_date'] as String,
  posSettings: PosSettings4.fromJson(
    json['pos_settings'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Branch11ToJson(Branch11 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'address': instance.address,
  'phone': instance.phone,
  'timezone': instance.timezone,
  'business_day_starts_at': instance.businessDayStartsAt,
  'business_date': instance.businessDate,
  'pos_settings': instance.posSettings,
};
