// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data34.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data34 _$Data34FromJson(Map<String, dynamic> json) => Data34(
  employee: User.fromJson(json['employee'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data34ToJson(Data34 instance) => <String, dynamic>{
  'employee': instance.employee,
  'invite_link': instance.inviteLink,
};
