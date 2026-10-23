// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter18.g.dart';

@JsonSerializable()
class Waiter18 {
  const Waiter18({required this.id, required this.name});

  factory Waiter18.fromJson(Map<String, Object?> json) =>
      _$Waiter18FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter18ToJson(this);
}
