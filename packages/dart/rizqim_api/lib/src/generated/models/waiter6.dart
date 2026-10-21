// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter6.g.dart';

@JsonSerializable()
class Waiter6 {
  const Waiter6({required this.id, required this.name});

  factory Waiter6.fromJson(Map<String, Object?> json) =>
      _$Waiter6FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter6ToJson(this);
}
