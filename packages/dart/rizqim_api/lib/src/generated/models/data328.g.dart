// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data328.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data328 _$Data328FromJson(Map<String, dynamic> json) => Data328(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data328ToJson(Data328 instance) => <String, dynamic>{
  'slots': instance.slots,
};
