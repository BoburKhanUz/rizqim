// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data285.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data285 _$Data285FromJson(Map<String, dynamic> json) => Data285(
  created: (json['created'] as num).toInt(),
  members: (json['members'] as List<dynamic>)
      .map((e) => Members2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data285ToJson(Data285 instance) => <String, dynamic>{
  'created': instance.created,
  'members': instance.members,
};
