// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data119.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data119 _$Data119FromJson(Map<String, dynamic> json) => Data119(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data119ToJson(Data119 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
