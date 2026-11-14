// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data286.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data286 _$Data286FromJson(Map<String, dynamic> json) => Data286(
  created: (json['created'] as num).toInt(),
  members: (json['members'] as List<dynamic>)
      .map((e) => Members2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data286ToJson(Data286 instance) => <String, dynamic>{
  'created': instance.created,
  'members': instance.members,
};
