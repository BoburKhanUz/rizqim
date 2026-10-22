// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken5.dart';
import 'applied5.dart';
import 'refunded5.dart';

part 'deposits5.g.dart';

@JsonSerializable()
class Deposits5 {
  const Deposits5({
    required this.taken,
    required this.applied,
    required this.refunded,
  });

  factory Deposits5.fromJson(Map<String, Object?> json) =>
      _$Deposits5FromJson(json);

  final Taken5 taken;
  final Applied5 applied;
  final Refunded5 refunded;

  Map<String, Object?> toJson() => _$Deposits5ToJson(this);
}
