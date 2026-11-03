// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data253.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data253 _$Data253FromJson(Map<String, dynamic> json) => Data253(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data253ToJson(Data253 instance) => <String, dynamic>{
  'slots': instance.slots,
};
