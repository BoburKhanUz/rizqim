// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data336.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data336 _$Data336FromJson(Map<String, dynamic> json) => Data336(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data336ToJson(Data336 instance) => <String, dynamic>{
  'slots': instance.slots,
};
