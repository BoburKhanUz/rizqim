// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data272.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data272 _$Data272FromJson(Map<String, dynamic> json) => Data272(
  session: json['session'] == null
      ? null
      : Session2.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data272ToJson(Data272 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
