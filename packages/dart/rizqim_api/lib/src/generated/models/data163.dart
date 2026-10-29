// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account.dart';
import 'entries2.dart';

part 'data163.g.dart';

@JsonSerializable()
class Data163 {
  const Data163({required this.account, required this.entries});

  factory Data163.fromJson(Map<String, Object?> json) =>
      _$Data163FromJson(json);

  final Account account;
  final List<Entries2> entries;

  Map<String, Object?> toJson() => _$Data163ToJson(this);
}
