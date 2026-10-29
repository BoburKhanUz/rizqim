// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data47.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data47 _$Data47FromJson(Map<String, dynamic> json) => Data47(
  branchId: (json['branch_id'] as num).toInt(),
  timezone: json['timezone'] as String,
  businessDayStartsAt: json['business_day_starts_at'] as String,
  posSettings: PosSettings2.fromJson(
    json['pos_settings'] as Map<String, dynamic>,
  ),
);

Map<String, dynamic> _$Data47ToJson(Data47 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'timezone': instance.timezone,
  'business_day_starts_at': instance.businessDayStartsAt,
  'pos_settings': instance.posSettings,
};
