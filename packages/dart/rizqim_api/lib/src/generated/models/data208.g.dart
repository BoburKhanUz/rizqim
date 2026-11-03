// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data208.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data208 _$Data208FromJson(Map<String, dynamic> json) => Data208(
  session: json['session'] == null
      ? null
      : Session4.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data208ToJson(Data208 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
