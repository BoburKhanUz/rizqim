// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data293.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data293 _$Data293FromJson(Map<String, dynamic> json) => Data293(
  created: (json['created'] as num).toInt(),
  members: (json['members'] as List<dynamic>)
      .map((e) => Members2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data293ToJson(Data293 instance) => <String, dynamic>{
  'created': instance.created,
  'members': instance.members,
};
