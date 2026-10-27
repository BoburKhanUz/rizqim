// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data56.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data56 _$Data56FromJson(Map<String, dynamic> json) => Data56(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data56ToJson(Data56 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
