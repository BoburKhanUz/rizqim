// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data290.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data290 _$Data290FromJson(Map<String, dynamic> json) => Data290(
  members: (json['members'] as List<dynamic>)
      .map((e) => Members.fromJson(e as Map<String, dynamic>))
      .toList(),
  activeCount: (json['active_count'] as num).toInt(),
);

Map<String, dynamic> _$Data290ToJson(Data290 instance) => <String, dynamic>{
  'members': instance.members,
  'active_count': instance.activeCount,
};
