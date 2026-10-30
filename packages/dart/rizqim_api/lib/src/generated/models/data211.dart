// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account.dart';
import 'entries3.dart';

part 'data211.g.dart';

@JsonSerializable()
class Data211 {
  const Data211({required this.account, required this.entries});

  factory Data211.fromJson(Map<String, Object?> json) =>
      _$Data211FromJson(json);

  final Account account;
  final List<Entries3> entries;

  Map<String, Object?> toJson() => _$Data211ToJson(this);
}
