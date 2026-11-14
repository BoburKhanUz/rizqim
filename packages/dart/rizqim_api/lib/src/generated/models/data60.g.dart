// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data60.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data60 _$Data60FromJson(Map<String, dynamic> json) => Data60(
  rates: (json['rates'] as List<dynamic>)
      .map((e) => Rates.fromJson(e as Map<String, dynamic>))
      .toList(),
  channels: json['channels'] as List<dynamic>,
);

Map<String, dynamic> _$Data60ToJson(Data60 instance) => <String, dynamic>{
  'rates': instance.rates,
  'channels': instance.channels,
};
