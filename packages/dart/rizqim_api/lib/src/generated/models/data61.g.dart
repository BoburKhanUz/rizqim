// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data61.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data61 _$Data61FromJson(Map<String, dynamic> json) => Data61(
  branchId: (json['branch_id'] as num).toInt(),
  timezone: json['timezone'] as String,
  businessDayStartsAt: json['business_day_starts_at'] as String,
  posSettings: PosSettings.fromJson(
    json['pos_settings'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Data61ToJson(Data61 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'timezone': instance.timezone,
  'business_day_starts_at': instance.businessDayStartsAt,
  'pos_settings': instance.posSettings,
};
