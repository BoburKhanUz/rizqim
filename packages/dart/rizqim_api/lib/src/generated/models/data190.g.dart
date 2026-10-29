// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data190.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data190 _$Data190FromJson(Map<String, dynamic> json) => Data190(
  session: json['session'] == null
      ? null
      : Session3.fromJson(json['session'] as Map<String, dynamic>),
  todayMinutes: (json['today_minutes'] as num).toInt(),
  businessDate: json['business_date'] as String,
);

Map<String, dynamic> _$Data190ToJson(Data190 instance) => <String, dynamic>{
  'session': instance.session,
  'today_minutes': instance.todayMinutes,
  'business_date': instance.businessDate,
};
