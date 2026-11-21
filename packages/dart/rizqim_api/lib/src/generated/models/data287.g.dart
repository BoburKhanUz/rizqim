// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data287.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data287 _$Data287FromJson(Map<String, dynamic> json) => Data287(
  members: (json['members'] as List<dynamic>)
      .map((e) => Members.fromJson(e as Map<String, dynamic>))
      .toList(),
  activeCount: (json['active_count'] as num).toInt(),
);

Map<String, dynamic> _$Data287ToJson(Data287 instance) => <String, dynamic>{
  'members': instance.members,
  'active_count': instance.activeCount,
};
