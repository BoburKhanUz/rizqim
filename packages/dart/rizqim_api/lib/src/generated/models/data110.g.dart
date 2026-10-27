// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data110.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data110 _$Data110FromJson(Map<String, dynamic> json) => Data110(
  staff: Staff.fromJson(json['staff'] as Map<String, dynamic>),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data110ToJson(Data110 instance) => <String, dynamic>{
  'staff': instance.staff,
  'invite_link': instance.inviteLink,
};
