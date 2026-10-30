// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff3.dart';

part 'holders.g.dart';

@JsonSerializable()
class Holders {
  const Holders({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders.fromJson(Map<String, Object?> json) =>
      _$HoldersFromJson(json);

  final String mode;
  final Staff3? staff;
  final String amount;

  Map<String, Object?> toJson() => _$HoldersToJson(this);
}
