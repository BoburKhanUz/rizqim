// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account.dart';
import 'entries2.dart';

part 'data164.g.dart';

@JsonSerializable()
class Data164 {
  const Data164({required this.account, required this.entries});

  factory Data164.fromJson(Map<String, Object?> json) =>
      _$Data164FromJson(json);

  final Account account;
  final List<Entries2> entries;

  Map<String, Object?> toJson() => _$Data164ToJson(this);
}
