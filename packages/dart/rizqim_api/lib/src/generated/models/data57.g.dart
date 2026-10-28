// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data57.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data57 _$Data57FromJson(Map<String, dynamic> json) => Data57(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data57ToJson(Data57 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
