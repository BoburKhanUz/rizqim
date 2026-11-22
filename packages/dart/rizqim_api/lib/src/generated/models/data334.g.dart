// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data334.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data334 _$Data334FromJson(Map<String, dynamic> json) => Data334(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data334ToJson(Data334 instance) => <String, dynamic>{
  'slots': instance.slots,
};
