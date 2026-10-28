// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data100.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data100 _$Data100FromJson(Map<String, dynamic> json) => Data100(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data100ToJson(Data100 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
