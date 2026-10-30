// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data252.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data252 _$Data252FromJson(Map<String, dynamic> json) => Data252(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data252ToJson(Data252 instance) => <String, dynamic>{
  'slots': instance.slots,
};
