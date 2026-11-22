// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data292.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data292 _$Data292FromJson(Map<String, dynamic> json) => Data292(
  created: (json['created'] as num).toInt(),
  members: (json['members'] as List<dynamic>)
      .map((e) => Members2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data292ToJson(Data292 instance) => <String, dynamic>{
  'created': instance.created,
  'members': instance.members,
};
