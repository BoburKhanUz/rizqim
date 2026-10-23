// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'applied.g.dart';

@JsonSerializable()
class Applied {
  const Applied({required this.count, required this.amount});

  factory Applied.fromJson(Map<String, Object?> json) =>
      _$AppliedFromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$AppliedToJson(this);
}
