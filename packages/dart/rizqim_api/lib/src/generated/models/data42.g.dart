// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data42.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data42 _$Data42FromJson(Map<String, dynamic> json) => Data42(
  branchId: (json['branch_id'] as num).toInt(),
  timezone: json['timezone'] as String,
  businessDayStartsAt: json['business_day_starts_at'] as String,
  posSettings: PosSettings2.fromJson(
    json['pos_settings'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Data42ToJson(Data42 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'timezone': instance.timezone,
  'business_day_starts_at': instance.businessDayStartsAt,
  'pos_settings': instance.posSettings,
};
