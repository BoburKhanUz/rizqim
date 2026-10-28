// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter20.g.dart';

@JsonSerializable()
class Waiter20 {
  const Waiter20({required this.id, required this.name});

  factory Waiter20.fromJson(Map<String, Object?> json) =>
      _$Waiter20FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter20ToJson(this);
}
