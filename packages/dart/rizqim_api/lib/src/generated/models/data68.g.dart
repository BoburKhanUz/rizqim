// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data68.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data68 _$Data68FromJson(Map<String, dynamic> json) => Data68(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data68ToJson(Data68 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
