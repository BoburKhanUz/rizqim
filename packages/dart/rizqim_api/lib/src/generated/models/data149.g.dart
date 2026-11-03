// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data149.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data149 _$Data149FromJson(Map<String, dynamic> json) => Data149(
  calendar: Calendar.fromJson(json['calendar'] as Map<String, dynamic>),
  tariffs: (json['tariffs'] as List<dynamic>)
      .map((e) => Tariffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data149ToJson(Data149 instance) => <String, dynamic>{
  'calendar': instance.calendar,
  'tariffs': instance.tariffs,
};
