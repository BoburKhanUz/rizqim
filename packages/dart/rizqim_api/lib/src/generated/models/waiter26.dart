// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter26.g.dart';

@JsonSerializable()
class Waiter26 {
  const Waiter26({required this.id, required this.name});

  factory Waiter26.fromJson(Map<String, Object?> json) =>
      _$Waiter26FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter26ToJson(this);
}
