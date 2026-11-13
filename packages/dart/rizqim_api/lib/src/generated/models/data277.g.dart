// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data277.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data277 _$Data277FromJson(Map<String, dynamic> json) => Data277(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data277ToJson(Data277 instance) => <String, dynamic>{
  'slots': instance.slots,
};
