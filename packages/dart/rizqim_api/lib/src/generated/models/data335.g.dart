// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data335.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data335 _$Data335FromJson(Map<String, dynamic> json) => Data335(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data335ToJson(Data335 instance) => <String, dynamic>{
  'slots': instance.slots,
};
