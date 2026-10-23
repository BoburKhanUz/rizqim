// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken4.dart';
import 'applied4.dart';
import 'refunded4.dart';

part 'deposits4.g.dart';

@JsonSerializable()
class Deposits4 {
  const Deposits4({
    required this.taken,
    required this.applied,
    required this.refunded,
  });

  factory Deposits4.fromJson(Map<String, Object?> json) =>
      _$Deposits4FromJson(json);

  final Taken4 taken;
  final Applied4 applied;
  final Refunded4 refunded;

  Map<String, Object?> toJson() => _$Deposits4ToJson(this);
}
