// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'actor.g.dart';

@JsonSerializable()
class Actor {
  const Actor({required this.id, required this.name, required this.role});

  factory Actor.fromJson(Map<String, Object?> json) => _$ActorFromJson(json);

  final int? id;
  final String name;
  final String role;

  Map<String, Object?> toJson() => _$ActorToJson(this);
}
