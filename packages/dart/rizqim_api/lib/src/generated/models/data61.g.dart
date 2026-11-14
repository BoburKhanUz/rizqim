// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data61.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data61 _$Data61FromJson(Map<String, dynamic> json) => Data61(
  rates: (json['rates'] as List<dynamic>)
      .map((e) => Rates2.fromJson(e as Map<String, dynamic>))
      .toList(),
  channels: json['channels'] as List<dynamic>,
);

Map<String, dynamic> _$Data61ToJson(Data61 instance) => <String, dynamic>{
  'rates': instance.rates,
  'channels': instance.channels,
};
