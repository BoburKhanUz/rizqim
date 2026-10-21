// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data45.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data45 _$Data45FromJson(Map<String, dynamic> json) => Data45(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data45ToJson(Data45 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
