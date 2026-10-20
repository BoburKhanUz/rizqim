// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

User2 _$User2FromJson(Map<String, dynamic> json) => User2(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  role: json['role'] as String,
);

Map<String, dynamic> _$User2ToJson(User2 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'role': instance.role,
};
