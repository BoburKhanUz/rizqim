// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data284.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data284 _$Data284FromJson(Map<String, dynamic> json) => Data284(
  members: (json['members'] as List<dynamic>)
      .map((e) => Members.fromJson(e as Map<String, dynamic>))
      .toList(),
  activeCount: (json['active_count'] as num).toInt(),
);

Map<String, dynamic> _$Data284ToJson(Data284 instance) => <String, dynamic>{
  'members': instance.members,
  'active_count': instance.activeCount,
};
