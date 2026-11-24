// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data291.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data291 _$Data291FromJson(Map<String, dynamic> json) => Data291(
  members: (json['members'] as List<dynamic>)
      .map((e) => Members.fromJson(e as Map<String, dynamic>))
      .toList(),
  activeCount: (json['active_count'] as num).toInt(),
);

Map<String, dynamic> _$Data291ToJson(Data291 instance) => <String, dynamic>{
  'members': instance.members,
  'active_count': instance.activeCount,
};
