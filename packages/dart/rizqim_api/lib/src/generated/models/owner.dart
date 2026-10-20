// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'owner.g.dart';

@JsonSerializable()
class Owner {
  const Owner({
    required this.name,
    required this.phone,
    required this.inviteLink,
    required this.hasJoined,
  });

  factory Owner.fromJson(Map<String, Object?> json) => _$OwnerFromJson(json);

  final String name;
  final String? phone;
  @JsonKey(name: 'invite_link')
  final String inviteLink;
  @JsonKey(name: 'has_joined')
  final bool hasJoined;

  Map<String, Object?> toJson() => _$OwnerToJson(this);
}
