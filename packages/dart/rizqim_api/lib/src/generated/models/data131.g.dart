// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data131.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data131 _$Data131FromJson(Map<String, dynamic> json) => Data131(
  staff: Staff.fromJson(json['staff'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data131ToJson(Data131 instance) => <String, dynamic>{
  'staff': instance.staff,
  'invite_link': instance.inviteLink,
};
