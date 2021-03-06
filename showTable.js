//PENDING: alert date format when #where .column changed to date column?
//PENDING: readable error messages

//modified from http://weblog.west-wind.com/posts/2009/Sep/07/Get-and-Set-Querystring-Values-in-JavaScript
function changeQueryString(key, value, query) {
	var q = query || window.location.search;
	q += "&";
	var re = new RegExp("[?|&]" + key + "=.*?&");
	if (re.test(q)) {
		q = q.replace(re, "&" + key + "=" + encodeURIComponent(value) + "&");
	} else {
		q += key + "=" + encodeURI(value);
	}
	q = q.trim("&");
	return q[0] === "?" ? q : "?" + q;
}
String.prototype.trimEnd = function(c) {
	c = c || " ";
	return this.replace(new RegExp(c.escapeRegExp() + "*$"), '');
};
String.prototype.trimStart = function(c) {
	c = c || " ";
	return this.replace(new RegExp("^" + c.escapeRegExp() + "*"), '');
};
String.prototype.trim = function(c) {
	c = c || " ";
	return this.trimStart(c).trimEnd(c);
};
String.prototype.escapeRegExp = function() {
	return this.replace(/[.*+?^${}()|[\]\/\\]/g, "\\$0");
};
$(function() {
	$("th, td").tooltipOnOverflow();
	$("select.defaultnull").each(function(index, element) {
		$(element).val("").removeClass("defaultnull");
	});
	$("#orderby .new .column").change(function() {
		var $parent = $(this).parent();
		var $new = $parent.clone(true).insertAfter($parent);
		$parent.removeClass("new");
		$("select", $new).val("");
		$(".asc", $new).val("ASC");
		$(this).unbind("change");
	});
	$("#where .and").change(function() {
		var $parent = $(this).parent();
		if($parent.hasClass("new")){
			var $new = $parent.clone(true).insertAfter($parent);
			$parent.removeClass("new");
			$(".bpar, .epar", $new).removeClass("checked");
			$("select", $new).val("");
			$(".value", $new).html("<input type='text' class='val' />");
		}
	});
	$("#orderby").click(function(e) {
		var $target = $(e.target);
		if ($target.hasClass("close")) {
			var $parent = $target.parent().parent();
			if ($parent.hasClass("new")) {
				$("select", $parent).val("");
				$(".asc", $parent).val("ASC");
			} else {
				$parent.remove();
			}
		}
	});
	$("#where").click(function(e) {
		var $target = $(e.target);
		if ($target.hasClass("close")) {
			var $parent = $target.parent().parent();
			if ($parent.hasClass("new")) {
				if($parent.prev().length === 0){
					$(".bpar, .epar", $parent).removeClass("checked");
					$(".value", $parent).html("<input type='text' class='val'>");
					$("select", $parent).val("");
					$(".asc", $parent).val("ASC");
				} else {
					var $prev = $parent.prev();
					$prev.addClass("new");
					$(".and", $prev).val("");
					$parent.remove();
				}
			} else {
				$parent.remove();
			}
		}
	});
	$("#where .bpar, #where .epar").click(function() {
		$(this).toggleClass("checked");
	});
	$("#submit").click(function() {
		$(".error").removeClass("error");
		var vars = {
			select: new Array(),
			orderby: new Array(),
			where: new Array(),
			limit: parseInt($("#limit").val(), 10)
		};
		var errors = new Array();
		var addError = function(err, $element) {
			errors.push(err);
			$element.addClass("error");
		};

		if ($("#select .column option:not(:selected)").length > 0) {
			vars.select = $("#select .column").val();
		}
		$(".orderby").not(".new").each(function(i, element) {
			var column = "";
			var asc = "";
			var $column = $(".column", element);
			var $asc = $(".asc", element);
			//validation
			column = $column.val();
			asc = $asc.val();

			vars.orderby.push(column + " " + asc);
		});
		
		//counts nested parentheses.
		//If an ending parenthesis shows up with out a beginning parenthesis than an error is thrown.
		//If there are more beginning parentheses than ending parentheses than an error is thrown.
		var parNest = 0;
		$(".where").each(function(i, element) {
			var isLast = $(this).hasClass("new");
			var useLast = true;
			var $bpar = $(".bpar", element);
			var $column = $(".column", element);
			var $operation = $(".operation", element);
			var $value = $(".value", element);
			var $epar = $(".epar", element);
			var $and = $(".and", element);
			var bpar = "";
			var column = $column.val();
			var operation = $operation.val();
			var value = "";
			var epar = "";
			var and = $and.val() || "";


			//validation
			if ($bpar.hasClass("checked")) {
				parNest++;
				bpar = "(";
			}
			if (column === null) {
				if (!isLast) {
					addError("condition column cannot be blank.", $column);
				} else {
					useLast = false;
				}
			}
			switch (operation) {
				case null:
					if (!isLast) {
						addError("condition operation cannot be blank.", $operation);
					} else {
						useLast = false;
					}
					break;
				case "between":
				case "notbetween":
					value = $(".val1", $value).val() + " AND " + $(".val2", $value).val();
					//PENDING: error if .val1 >= .val2?
					break;
				case "isnull":
				case "isnotnull":
					break;
				default:
					value = $(".val", $value).val();
					break;
			}
			if ($epar.hasClass("checked")) {
				if (parNest === 0) {
					addError("missing beginning parenthesis to match", $epar);
				}
				epar = ")";
				parNest--;
			}
			if (!isLast || useLast) {
				vars.where.push(bpar, column, operation, value, epar, and);
			}
		});
		if (parNest !== 0) {
			addError("not enough beginning parentheses.", $(".bpar, .epar"));
		}
		if(isNaN(vars.limit)){
			addError("must be a number", $("#limit"));
		}
		if (errors.length === 0) {
			location.href = "?" + $.param(vars);
		} else {
			alert(errors.join("\n"));
		}
	});
	$(".first, .last, .previous, .next").click(function() {
		location.href = changeQueryString("start", $(this).data("start"));
	});
	$("#where .operation").change(function() {
		var $this = $(this);
		var $value = $this.siblings(".value");
		var value = $this.data("val");
		if ($(".val", $value).length > 0) {
			value = $(".val", $value).val();
			$this.data("val", value);
		}

		switch ($this.val()) {
			case "between":
			case "notbetween":
				$value.html("<input type='text' class='val1' value='' /> AND <input type='text' class='val2' />");
				break;
			case "isnull":
			case "isnotnull":
			case "istrue":
			case "isfalse":
				$value.html("");
				break;
			default:
				$value.html("<input type='text' class='val' value='" + value + "' />");
				break;
		}
	});
	$("#reset").click(function() {
		location.search = "";
//		$(".close").click();
//		$("#select .column option").prop("selected", false);
//		$("#select .column option[data-default=1]").prop("selected", true);
	});
});
//jquery plugin for showing tooltip on overflow
(function($) {
	'use strict';
	$.fn.tooltipOnOverflow = function() {
		$(this).on("mouseenter", function() {
			if (this.offsetWidth < this.scrollWidth) {
				$(this).attr('title', $(this).text());
			} else {
				$(this).removeAttr("title");
			}
		});
	};
})(jQuery);
