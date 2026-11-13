// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data232.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data232 _$Data232FromJson(Map<String, dynamic> json) => Data232(
  members: (json['members'] as List<dynamic>)
      .map((e) => Members.fromJson(e as Map<String, dynamic>))
      .toList(),
  activeCount: (json['active_count'] as num).toInt(),
);

Map<String, dynamic> _$Data232ToJson(Data232 instance) => <String, dynamic>{
  'members': instance.members,
  'active_count': instance.activeCount,
};
