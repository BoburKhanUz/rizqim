// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'owner.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Owner _$OwnerFromJson(Map<String, dynamic> json) => Owner(
  name: json['name'] as String,
  phone: json['phone'] as String?,
  inviteLink: json['invite_link'] as String,
  hasJoined: json['has_joined'] as bool,
);

Map<String, dynamic> _$OwnerToJson(Owner instance) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'invite_link': instance.inviteLink,
  'has_joined': instance.hasJoined,
};
